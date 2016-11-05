<?php

use PHPHtmlParser\Exceptions\CurlException;

class Curl implements PHPHtmlParser\CurlInterface
{
    /**
     * @param string $url
     * @return mixed
     * @throws CurlException
     */
    public function get($url)
    {
        $ch = curl_init($url);

        if ( ! ini_get('open_basedir')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        // На taktak мутный серт.
        // Отключаем проверку серта, ни каких секретных данных все равно не передаём
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $content = curl_exec($ch);
        if ($content === false) {
            // there was a problem
            $error = curl_error($ch);
            throw new CurlException('Error retrieving "'.$url.'" ('.$error.')');
        }

        return $content;
    }

}