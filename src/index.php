<?php

class parse_drive2{

    function getLinks($__search, $__users = false, $__links = array()){
        $users = $__users
            ?   '&category=Users'
            :   '';
        $page = file_get_contents('https://www.drive2.ru/search/?text='.urlencode($__search).$users);
        if (preg_match('/Поиск\sничего\sне\sнашёл/uis', $page, $nothing)){
            return 'Ничего не найдено!';
        }
        preg_match('/title=\"([0-9]+)\">Последняя<\/a>/uis', $page, $pages);
        for ($i = 0; $i < $pages[1]; ++$i){
            $finds = array();
            if (preg_match_all('/<a\sclass=\"c-block\sc-serp-item\"\shref=\"(https:\/\/www\.drive2\.ru\/users\/.+?\/)/uis', file_get_contents('https://www.drive2.ru/search/?page='.$i.'&text='.urlencode($__search).$users), $finds)){
                foreach($finds[1] as $link){
                    if (!in_array($link, $__links)){
                        $__links[] = $link;
                    }
                }
            }
        }
        if (!$__users){
            return $this->getLinks($__search, true, $__links);
        }
        return $__links;
    }
    
    function parse($__link, $__mysql){
        $curl_handle=curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $__link);
        curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        $site = curl_exec($curl_handle);
        if (curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) == 404){
            return 0;
        }
        curl_close($curl_handle);
        $pattern = '/<span\sitemprop=\"name\">(?<nickname>.*?)<\/span>
        .*?class=\"c-user-card__status.*?\".*?>(?<last_time_was_online>.*?)<\/
        (.*?<span\sclass=\"c-user-card__imp\">\s*?<span>(?<name>.*?)<\/span>)?
        (.*?<span\sclass=\"c-user-card__age\">(?<age>[0-9]+).*?<\/span>)?
        (.*?<span\sclass=\"c-user-card__virtual-car\">Я\sезжу\sна\s(?<car>.*?)<\/span>)?
        (.*?<span\sitemprop=\"address\"><span\stitle="(?<city>.*?)">.*?<\/span>)?
        ';
        $countOfCars = preg_match_all('/data-narrow.*?data-type=\"car\".*?data-tt=\"Подписаться\"/uis', $site);
        $isManyCars = false;
        if ($countOfCars > 1){
            $isManyCars = true;
        }
        for ($j = 0; $j < $countOfCars; $j++){
            $pattern .= '.*?<svg.*?<a\sclass=\"c-car-title\s\sc-link.*?>(?<car_'.$j.'>.*?)<\/a>
            ';
        }
        $pattern .= '(.*?<div\sclass=\"user-about\stext\stext--xs\">.*?<p>.*?<button.*?<div\sid=\"user-about-full\"\sstyle=\"display\:\snone\;\">.*?<p>(?<about_yourself>.*?)<\/div>
        |
        .*?<div\sclass=\"user-about\stext\stext--xs\">.*?<p>(?<About_yourself>.*?)<\/div>)?
        (.*?<div\sclass=\"c-ministats__item\">.*?<span\sdata-tt=\"(?<registration_date>.*?)\">.*?<\/span>.*?<\/div>.*?<div\sclass=\"c-ministats__item\">\s(?<count_of_comments>.*?)<\/div>.*?<\/div>)?/xuis';
        preg_match($pattern, $site, $arr);
        $inserts = '';
        $values = '';
        $firstCar = true;
        $lastCar = false;
        foreach($arr as $key => $value){
            if (!is_numeric($key) && $value != ''){
                if (preg_match('/car/uis', $key)){
                    if (!$isManyCars){
                        $inserts .= 'cars, ';
                        $values .= '\''.trim(strip_tags($value)).'\', ';
                        continue;
                    }
                    elseif($firstCar){
                        $inserts .= 'cars, ';
                        $values .= '\''.trim(strip_tags($value)).', ';
                        $firstCar = false;
                        continue;
                    }
                    else{
                        $lastCar = true;
                        $values .= trim(strip_tags($value)).', ';
                        continue;
                    }
                }
                if ($lastCar){
                    $lastCar = false;
                    $values = substr($values, 0, -2).'\', ';
                }
                if($key == 'count_of_comments'){
                    $count = 0;
                    if (preg_match('/[0-9]+/uis', $value, $temp)){
                        $count = $temp[0];
                    }
                    $inserts .= strtolower($key).', ';
                    $values .= '\''.$count.'\', ';
                }
                else{
                    $inserts .= strtolower($key).', ';
                    $values .= '\''.trim(strip_tags($value)).'\', ';
                }
            }
        }
        $query = "INSERT INTO user (".$inserts."link) VALUE (".$values."'$__link')";
        mysqli_query($__mysql, $query);
    }
}