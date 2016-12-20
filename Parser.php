<?php

/**
 * Парсер комментов пользователя на ТакТакТак
 */
class Parser
{
    /** @var int id отбрабатываемого пользователя */
    protected $_userId;

    /** @var int обрабатываемый месяц */
    protected $_month;

    /** @var int обрабатываемый год */
    protected $_year;

    /** @var int ограничение минимального время проблемы. */
    protected $_minProblemDate;

    /** @var int время сна после запроса к так так так, сек */
    protected $_sleep;

    /** @var array накопленная статистика */
    protected $_stat = [];

    /** @var PHPHtmlParser\Dom парсер DOM */
    protected $_dom;

    /** @var PHPHtmlParser\CurlInterface */
    protected $_curl;

    /**
     * @param int $userId
     * @param int $month
     * @param int $year
     * @param int $monthDeep
     * @param int $sleep
     * @throws Parser_Exception
     */
    public function __construct($userId, $month, $year, $monthDeep, $sleep)
    {
        if (empty($userId)) {
            throw new Parser_Exception("Пустой userId");
        }

        if (!checkdate($month, 1, $year)) {
            throw new Parser_Exception("Неверный месяц($month) или год($year)");
        }

        $this->_minProblemDate = $this->_getMinProblemDate($month, $year, $monthDeep);

        $this->_userId = intval($userId);
        $this->_month = $month;
        $this->_year = $year;

        $this->_sleep = $sleep;

        $this->_dom = new PHPHtmlParser\Dom();
        $this->_curl = new Curl();
    }

    protected function _getMinProblemDate($month, $year, $monthDeep)
    {
        $endDate = new DateTime();
        $endDate->setDate($year, $month, 1)
            ->setTime(0, 0, 0)
            ->sub(new DateInterval('P' . $monthDeep . 'M'));

        return $endDate;

    }

    /**
     * Возвращает URL страницы ответов пользователя
     *
     * @param int $page
     * @return string
     */
    protected function _getAnswerUrl($page)
    {
        return sprintf(
            "https://taktaktak.ru/person/%d/answers?page=%d&ajax=2",
            $this->_userId,
            $page
        );
    }

    /**
     * Возвращает URL проблемы
     *
     * @param int $problemId
     * @return string
     */
    protected function _getProblemUrl($problemId)
    {
        return sprintf(
            "https://taktaktak.ru/problem/%d",
            $problemId
        );
    }

    /**
     * Обработка
     *
     * @throws Parser_Exception
     */
    public function parseUser()
    {
        printf(
            "Ищу решения за %d/%d в проблемах с %s\n",
            $this->_month,
            $this->_year,
            $this->_minProblemDate->format('d.m.Y H:i:s')
        );
        $html = $this->_dom->loadFromUrl($this->_getAnswerUrl(1), [], $this->_curl);
        sleep($this->_sleep);

        $paginator = $html->find(".paginator a", 0)->text;
        if (!preg_match("/(\d+) из (\d+)/", $paginator, $matches)) {
            throw new Parser_Exception("Не могу распарсить пагинатор");
        }
        echo "Листалка: " . $matches[0] . PHP_EOL;
        $totalProblems = $matches[2];
        $problemsPerPage = $matches[1];

        for ($page = 1; $page <= ceil($totalProblems / $problemsPerPage); $page++ ) {
            $res = $this->_parseComments($page);
            if ($res === false) {
                break;
            }
        }
    }

    /**
     * Возвращает массив собранной статистики
     *
     * @return array
     */
    public function getStat()
    {
        return $this->_stat;
    }


    /**
     * Парсит ajax страницу c ответами пользоватея
     *
     * @param int $page
     * @return bool true - нормальный режим. false - превышено ограничение по времени проблемы
     */
    protected function _parseComments($page)
    {
        echo "Страница $page" . PHP_EOL;
        $html = $this->_dom->loadFromUrl($this->_getAnswerUrl($page), [], $this->_curl);
        sleep($this->_sleep);
        echo "======================================" . PHP_EOL;

        foreach($html->find('h3 a') as $item) {
            echo $item->text  . ' (' . $item->href . ') ';
            $problemUrl = $this->_getProblemUrl(explode('/', $item->href)[2]);
            $comment = $this->_parseProblemPage($problemUrl);
            if ($comment === false) {
                return false;
            }
            if ($comment) {
                echo "Решение: " . date ("Y-m-d", $comment['date']);
                $this->_stat[] = [
                    'comment_date' => $comment['date'],
                    'problem_name' => $item->text,
                    'problem_url'  => $problemUrl,
                ];
            }
            echo PHP_EOL;
        }
        echo "======================================" . PHP_EOL;
        return true;
    }


    /**
     * Парсит ответы пользователя на странице проблемы
     *
     * @param string $problemUrl
     * @return array|false данные первого найденого коментария или
     *          false если превышено ограничение по времени проблемы
     */
    protected function _parseProblemPage($problemUrl)
    {
        $html = $this->_dom->loadFromUrl($problemUrl, [], $this->_curl);
        sleep($this->_sleep);

        $res = [];

        $problemDate = $html->find("#rightSticky .list", 0)->innerhtml;
        /** @var \PHPHtmlParser\Dom\HtmlNode $problemDate */
        preg_match("/(.+),[^<]*<br \/>/", $problemDate, $m);

        $problemDate = $this->_getTime($m[1]);
        echo date("Y-m-d", $problemDate) . " ";
        if ($problemDate < $this->_minProblemDate->getTimestamp()) {
            echo PHP_EOL . "Превышено ограничение по времени" . PHP_EOL;
            return false;
        }

        $userComments = $html->find("div.answer a[href=/person/" . $this->_userId . "]");
        foreach ($userComments as $commentItem) {
            /** @var PHPHtmlParser\Dom\HtmlNode $commentItem */
            $dateStrNode = $commentItem->getParent()->getParent()->find(".date span", 0);
            // у удалённых комментов нет даты
            if (!$dateStrNode) {
                continue;
            }
            $dateStr = $dateStrNode->text();
            $date = $this->_getTime($dateStr);
            if (date('Y', $date) != $this->_year || date('n', $date) != $this->_month) {
                continue;
            }

            $res = ['date' => $date];
            break;
        }

        return $res;
    }

    /**
     * Сортирует массив по указанному полю
     *
     * @param array $stat мсссив для сортировки
     * @param string $field название поля
     * @return array отсортированный массив
     */
    public static function sortByField(array $stat, $field)
    {
        $cmp = function ($a, $b) use ($field) {
            if ($a[$field] == $b[$field]) {
                return 0;
            }
            return ($a[$field] < $b[$field]) ? -1 : 1;
        };
        usort($stat, $cmp);
        return $stat;
    }


    /**
     * Конвертирует время из формата так така в unixtimestamp
     *
     * @param string $date
     * @return int
     */
    protected function _getTime($date)
    {
        $date = str_replace(
            [
                'сегодня',
                'вчера',
                'января',
                'февраля',
                'марта',
                'апреля',
                'мая',
                'июня',
                'июля',
                'августа',
                'сентября',
                'октября',
                'ноября',
                'декабря'
            ],
            [
                'today',
                'yesterday' ,
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December'
            ],
            $date
        );
        return strtotime($date);
    }
}
