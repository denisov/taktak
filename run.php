<?php

require_once "vendor/autoload.php";
require_once "Parser.php";
require_once "Curl.php";
require_once "Parser/Exception.php";

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Novosibirsk');

$doc = <<<DOC
Usage: run.php [--user=<user_id>] [--month=<month>] [--year=<year>] [--sleep=<sec>] [--month_deep=<n>]

Options:
  --user=<user_id>  id пользователя. Мила 15525. Марина 17881 [default: 15525]
  --month=<month>   месяц. По-умолчанию текущий.
  --year=<year>     год. По-умолчанию текущий.
  --month_deep=<n>  на сколько меяцев в прошлое уходить [default: 3]
  --sleep=<sec>     сон в сек между запросами [default: 1].
  
DOC;

$args = Docopt::handle($doc);
$userId = $args->args['--user'];
$month = $args->args['--month'] ? intval($args->args['--month']) : date('n');
$year = $args->args['--year'] ? intval($args->args['--year']) : date('Y');
$sleep = $args->args['--sleep'];
$monthDeep = $args->args['--month_deep'] - 1;

$parser = new Parser($userId, $month, $year, $monthDeep, $sleep);
$parser->parseUser();

$fileName = dirname(__FILE__) . '/reports/' . sprintf(
        "report_%d_%d_%d-%s.csv",
        $userId,
        $year,
        $month,
        date("YmdHis")
    );
echo "Создаю отчёт $fileName  ....";
$fp = fopen($fileName, 'w');
$header = ['№', 'Даты консультации', 'Вопрос', 'Краткий комментарий (ответ)'];
fputcsv($fp, $header);
$no = 1;
foreach (Parser::sortByField($parser->getStat(), 'comment_date') as $statStr) {
    array_unshift($statStr, $no);
    $statStr['comment_date'] = date('d.m.Y', $statStr['comment_date']);
    fputcsv($fp, $statStr);
    $no++;
}
fclose($fp);

