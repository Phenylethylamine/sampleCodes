<?php
class Elasticsearch
{
    public $URI = array('host' => 'http://192.168.0.1:9200');

    /**
     * 싱글톤 패턴 정의
     */
    public static $instance = null;

    public static function getInstance($action, $index = '')
    {
        if (self::$instance === null) self::$instance = new self();
        self::$instance->set_mode($action, $index);
        return self::$instance;
    }

    protected function __construct()
    {
    }

    protected function __clone()
    {
    }

    protected function __wakeup()
    {
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    # _search || _sql
    public function set_mode($action, $index = '')
    {
        # cURL 초기화
        $this->ch = curl_init();

        # URI 준비
        switch ($action) {
            case '_search' :
                if ($index) $this->URI['index'] = $index;
                break;
            case '_analyze' :
                if ($index) $this->URI['index'] = $index;
                break;
            case '_sql' :
                break;
        }
        $this->URI['action'] = $action;

        # cURL 설정 적용
        $this->curl_option = array(
            # URI를 설정합니다
            CURLOPT_URL => $this->getURI(),
            # 프로세스 종로시 cURL을 종료합니다
            CURLOPT_FORBID_REUSE => true,
            # method 설정(true:post, false:get)
            CURLOPT_POST => true,
            # 헤더 정보 출력
            CURLOPT_HEADER => false,
            # return 설정
            CURLOPT_RETURNTRANSFER => true,
        );
        curl_setopt_array($this->ch, $this->curl_option);
    }

    public function getURI()
    {
        $URI = $this->URI['host'];
        if ($this->URI['index']) $URI .= '/' . $this->URI['index'];
        if ($this->URI['action']) $URI .= '/' . $this->URI['action'];
        return $URI;
    }
    /**
     * $data['_sql'] : (string)
     * $data['search'] : (jsonString)
     */
    public function exec($data, $return_type = 'json')
    {
        # elasticsearch는 소문자만 지원합니다
        # $data = strtolower($data);
        # 함수는 대문자 필요
        $data = str_replace('math.random()', 'Math.random()', $data);

        # DATA 설정
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);

        # CURL 실행
        $this->result = curl_exec($this->ch);

        # TEST
        if ($GLOBALS['printProcess']) {
            Dev::print("URI : " . $this->getURI(), "POST_DATA : " . print_r($data, 1), "result : {$this->result}");
        }
        return $this->result;
    }
}