<?php

/**
 * 11번가 기본 클래스
 * KEY : __KEY__
 */
class st11
{
    public $test = false;
    public $test_log = array();

    public function __destruct()
    {
        if ($this->test) {
            echoDev($this->test_log);
        }
    }

    /**
     * 11번가 데이터 요청
     */
    static function request($URL, $METHOD, $XML = '')
    {
        $ch = curl_init();
        $HEADER = array("Content-type: text/xml;charset=EUC-KR", "openapikey:__KEY__");
        if ($GLOBALS['print_process']) echoDev($URL);
        if ($XML) {
            $XML = "<?xml version='1.0' encoding='euc-kr'?>" . $XML;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $XML);

            if ($GLOBALS['print_process']) echoDev($XML);
        }
        curl_setopt($ch, CURLOPT_URL, $URL);
        #curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $HEADER);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        switch ($METHOD) {
            case 'POST' :
                curl_setopt($ch, CURLOPT_POST, true);
                break;
            case 'PUT' :
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'GET' :
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }
        $response = curl_exec($ch);
        curl_close($ch);
        if ($GLOBALS['print_process']) echoDev($response);
        return $response;
    }

    /**
     * 배열을 XML로 변환합니다
     * DOCTYPE은 생성되지 않습니다
     */
    static function array_to_xml($array)
    {
        $xml = '';
        foreach ($array AS $k => $v) {
            if (is_array($v)) {
                # $v가 인덱싱 배열일 경우
                if (isset($v[0])) {
                    for ($i = 0, $end = count($v); $i < $end; ++$i) {
                        $xml .= "<{$k}>" . self::array_to_xml($v[$i]) . "</{$k}>";
                    }
                } else {
                    $xml .= "<{$k}>" . self::array_to_xml($v) . "</{$k}>";
                }
            } else {
                $xml .= "<{$k}>{$v}</{$k}>";
            }
        }
        return $xml;
    }

    /**
     * XML을 배열로 변환합니다
     * simplexml_load_string->json_encode->json_decode 순서로 변환됩니다
     * simplexml로 변환할때 UTF-8로 자동변환 되기 때문에, json_decode 후 다시 EUC-KR로 변환됩니다.
     */
    static function xml_to_array($xml_string)
    {
        $xml_string = str_replace('ns2:', '', $xml_string);
        $xml = @simplexml_load_string($xml_string);
        if ($xml === false) return false;
        return self::recursive_null_array_to_null(util::recursive_iconv('utf8', 'euckr', json_decode(json_encode($xml), true)));

    }

    /**
     * xml_to_array에서 값이 없는 태그가 빈 배열로 인식되는 문제가 있어서
     * 이 함수로 보정해주었습니다
     */
    static function recursive_null_array_to_null($array)
    {
        foreach ($array AS $k => $v) {
            if (is_array($v)) {
                if (count($v) == 0)
                    $array[$k] = null;
                else
                    $array[$k] = self::recursive_null_array_to_null($v);
            }
        }
        return $array;
    }

    public function get_reg_date()
    {
        if (isset($this->reg_date)) return $this->reg_date;
        return $this->reg_date = date('Y-m-d H:i:s');
    }
}