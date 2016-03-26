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

    /** @var int время сна после запроса к так так так, сек */
    protected $_sleep;

    /** @var array накопленная статистика */
    protected $_stat = [];

    /** @var PHPHtmlParser\Dom парсер DOM */
    protected $_dom;

    /**
     * @param int $userId
     * @param int $month
     * @param int $year
     * @param int $sleep
     * @throws Parser_Exception
     */
    public function __construct($userId, $month, $year, $sleep)
    {
        if (empty($userId)) {
            throw new Parser_Exception("Пустой userId");
        }
        $this->_userId = intval($userId);
        $this->_month = $month;
        $this->_year = $year;

        $this->_sleep = $sleep;

        $this->_dom = new PHPHtmlParser\Dom();
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
            "http://taktaktak.org/person/%d/answers?page=%d&ajax=2",
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
            "http://taktaktak.org/problem/%d",
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
        $html = $this->_dom->loadFromUrl($this->_getAnswerUrl(1));
        sleep($this->_sleep);

        $paginator = $html->find(".paginator a", 0)->text;
        if (!preg_match("/(\d+) из (\d+)/", $paginator, $matches)) {
            throw new Parser_Exception("Не могу распарсить пагинатор");
        }
        echo "Листалка: " . $matches[0] . PHP_EOL;
        $totalProblems = $matches[2];
        $problemsPerPage = $matches[1];

        for ($page = 1; $page <= ceil($totalProblems / $problemsPerPage); $page++ ) {
            $this->_parseComments($page);
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
     */
    protected function _parseComments($page)
    {
        echo "Страница $page" . PHP_EOL;
        $html = $this->_dom->loadFromUrl($this->_getAnswerUrl($page));
        sleep($this->_sleep);
        echo "======================================" . PHP_EOL;

        foreach($html->find('h3 a') as $item) {
            echo $item->text  . ' (' . $item->href . ') ';
            $problemUrl = $this->_getProblemUrl(explode('/', $item->href)[2]);
            $comment = $this->_parseProblemPage($problemUrl);
            if ($comment) {
                echo "Найдено решение";
                $this->_stat[] = [
                    'comment_date' => $comment['date'],
                    'problem_name' => $item->text,
                    'problem_url'  => $problemUrl,
                ];
            }
            echo PHP_EOL;
        }
        echo "======================================" . PHP_EOL;
    }


    /**
     * Парсит ответы пользователя на странице проблемы
     *
     * @param string $problemUrl
     * @return array данные первого найденого коментария
     */
    protected function _parseProblemPage($problemUrl)
    {
        $html = $this->_dom->loadFromUrl($problemUrl);
        sleep($this->_sleep);

        $res = [];

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
