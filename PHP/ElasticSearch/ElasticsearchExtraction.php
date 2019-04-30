<?php

/**
 *
 */
class ElasticsearchExtraction
{
    public $index = '###상품인덱스명###';
    public $request;
    public $prequery;
    public $sort = '시작일순';
    public $size = 30;
    public $page = 1;
    public $query_dsl = array(
        '_source' => array(),
        'from' => 0,
        'size' => 30
    );

    # DSL 질의 이후 처리할 작업 목록을 담습니다
    public $post_process_list = array();

    public $sql_seller = array('SELECT' => array('HM.user_id'));
    public $seller_data = array();
    private $thumbnail_option;


    public function __construct($format = '')
    {
        $this->setFormat($format);
    }


    # STEP1 : 추출해야할 Field와 추출 후 처리해야할 작업 목록을 정의합니다.
    public function setFormat($args)
    {
        if ($args == '전체') {
            $args = '상품주소,상품명,즉시구매가,판매자정보,시작일,등록일,배송비,카테고리,사용기간,정품여부,상품코드,판매상태,누적판매량,누적판매순위,재고,섬네일';
        }
        if ($args == '안드로이드앱') {
            $this->addSource('number,product_code,product_stats,product_name,category,brand_name,location,id,susuryotype,sijoong_price,baro_price,start_date,reg_date,baesong_type,baesong_cut_free,product_sangtae,product_use_time,product_use_time_month,sell_count,jaego,wonsanji,wonsanji_text,baesong_tie,img1');
            $this->post_process_list = explode(',', '상품주소,즉시구매가,판매자정보,시작일,등록일,배송비,대분류명,사용기간,정품여부,섬네일,안드로이드앱,검색어강조');
            $this->thumbnail_option['width'] = 120;
            $this->thumbnail_option['height'] = 120;
            $this->sql_seller['SELECT'][] = "HM.power_seller";
            $this->sql_seller['SELECT'][] = "HM.user_nick";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'susuryo' LIMIT 1) AS susuryo";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'trust_point' LIMIT 1) AS trust_point";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_icon_seller' LIMIT 1) AS credit_icon_seller";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_level_seller' LIMIT 1) AS credit_level_seller";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_level_buyer' LIMIT 1) AS credit_level_buyer";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_icon_buyer' LIMIT 1) AS credit_icon_buyer";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_point_seller' LIMIT 1) AS credit_point_seller";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_point_buyer' LIMIT 1) AS credit_point_buyer";
            $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_price_seller' LIMIT 1) AS credit_price_seller";
            $this->sql_seller['SELECT'][] = "(SELECT sum_cnt FROM (지식인랭킹테이블) WHERE id = HM.user_id AND sum_cnt > 4) AS kin_sum";
            $this->sql_seller['SELECT'][] = "(SELECT COUNT(*) FROM (판매자신용점수테이블) WHERE aver_point >= 4.0 AND seller_id = HM.user_id) AS rating_good";
            $this->sql_seller['SELECT'][] = "(SELECT COUNT(*) FROM (판매자신용점수테이블) WHERE aver_point BETWEEN 2.0 AND 4.0 AND seller_id = HM.user_id) AS rating_soso";
            $this->sql_seller['SELECT'][] = "(SELECT COUNT(*) FROM (판매자신용점수테이블) WHERE aver_point < 2.0 AND seller_id = HM.user_id) AS rating_bad";
            return;
        }

        $args = explode(',', $args);
        for ($i = 0, $end = count($args); $i < $end; $i++) {
            $args[$i] = trim($args[$i]);
            switch ($args[$i]) {
                case '상품번호' :
                    $this->addSource('number');
                    break;
                case '상품주소' :
                    $this->addSource('number,category');
                    $this->post_process_list[] = '상품주소';
                    break;
                case '상품명' :
                    $this->addSource('product_name');
                    break;
                case '즉시구매가' :
                    $this->addSource('location,susuryotype,product_sangtae,brand_name,id,sijoong_price,baro_price,category');
                    $this->post_process_list[] = '즉시구매가';
                    # 판매자 수수료율
                    $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'susuryo' LIMIT 1) AS susuryo";
                    break;
                case '판매자정보' :
                    $this->sql_seller['SELECT'][] = "HM.power_seller";
                    $this->sql_seller['SELECT'][] = "HM.user_nick";
                    $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'trust_point' LIMIT 1) AS trust_point";
                    $this->sql_seller['SELECT'][] = "(SELECT option_value FROM (회원정보확장테이블) WHERE user_id = HM.user_id AND option_field = 'credit_icon_seller' LIMIT 1) AS credit_icon_seller";
                    $this->sql_seller['SELECT'][] = "(SELECT sum_cnt FROM (지식인랭킹테이블) WHERE id = HM.user_id AND sum_cnt > 4) AS kin_sum";
                    $this->post_process_list[] = '판매자정보';
                    break;
                case '시작일' :
                    $this->addSource('start_date');
                    $this->post_process_list[] = '시작일';
                    break;
                case '등록일' :
                    $this->addSource('reg_date');
                    $this->post_process_list[] = '등록일';
                    break;
                case '배송비' :
                    $this->addSource('baesong_type,baesong_cut_free');
                    $this->post_process_list[] = '배송비';
                    break;
                case '카테고리' :
                    $this->addSource('category');
                    $this->post_process_list[] = '대분류명';
                    break;
                case '사용기간' :
                    $this->addSource('product_sangtae,product_use_time,product_use_time_month');
                    $this->post_process_list[] = '사용기간';
                    break;
                case '정품여부' :
                    $this->addSource('derive');
                    $this->post_process_list[] = '정품여부';
                    break;
                case '상품코드' :
                    $this->addSource('product_code');
                    break;
                case '판매상태' :
                    $this->addSource('product_stats');
                    break;
                case '누적판매량' :
                    $this->addSource('sell_count');
                    break;
                case '누적판매순위' :
                    $this->post_process_list[] = '누적판매순위';
                    break;
                case '검색어강조' :
                    $this->post_process_list[] = '검색어강조';
                    break;
                case '재고' :
                    $this->addSource('jaego');
                    break;
                case '모델명' :
                    $this->addSource('model_name_k');
                    break;
                default :
                    if (strpos($args[$i], '섬네일') !== false) {
                        $this->addSource('img1');
                        $this->post_process_list[] = '섬네일';
                        $this->thumbnail_option = array();
                        preg_match('/\d+/', $args[$i], $match);
                        if (!$match[0] || $match[0] == '288' || $match[0] == '290') {
                            $this->thumbnail_option['width'] = 288;
                            $this->thumbnail_option['height'] = 290;
                        } else {
                            $this->thumbnail_option['width'] = $match[0];
                            $this->thumbnail_option['height'] = $match[0];
                        }
                    }
                    break;
            }
        }
    }

    public function addSource($source)
    {
        $this->query_dsl['_source'] = array_unique(array_merge($this->query_dsl['_source'], explode(',', $source)), SORT_STRING);
    }

    public function setPagination($size = null, $page = null)
    {
        if (is_numeric($size) == false) $size = $_GET['main_pagescale'] ? $_GET['main_pagescale'] : 30;
        if (is_numeric($page) == false) $page = ($_GET['page']) ? $_GET['page'] : 1;
        $this->size = $size;
        $this->page = $page;
        $this->query_dsl['from'] = ($page - 1) * $size;
        $this->query_dsl['size'] = $size;
    }


    # request로 들어오는 변수를 prequery 형식으로 변환합니다
    public function requestToPrequery($request)
    {
        /*
        [request로 들어오는 변수]
        nowlocation
        brand_name
        category,company,type
        model_name
        selectedtabmenutype
        ag_id
        search_word,search_type
        */
        $prequery = array();

        # nowlocation
        if ($request['nowlocation'] == '3') {
            $prequery['location'] = array('3');
            $prequery['product_sangtae'] = '0';
        } elseif ($request['nowlocation'] == '2') {
            $prequery['location'] = array('1', '2');
            if ($request['ag_id'])
                $prequery['id'] = array($request['ag_id']);
            else {
                $prequery['id'] = array();
                $agency_list = marketData::getInstance()->getAgencyList();
                for ($i = 0, $end = count($agency_list); $i < $end; ++$i) {
                    $prequery['id'][] = $agency_list[$i]['user_id'];
                }
            }

        } else {
            $prequery['location'] = array('1', '2', '3');
        }

        # brand_name
        if ($request['brand_name']) $prequery['brand_name'] = $request['brand_name'];

        # category
        if ($request['type']) $last_category = $request['type'];
        elseif ($request['company']) $last_category = $request['company'];
        elseif ($request['category']) $last_category = $request['category'];
        if ($last_category) {
            if (count(explode('r', $last_category)) == 1)
                $category_prefix = $last_category . 'r';
            else
                $category_prefix = $last_category;

            $prequery['category'] = $category_prefix;
        }

        # model_name
        if ($request['model_name']) $prequery['model_name'] = $request['model_name'];

        # selectedtabmenutype
        switch ($request['selectedtabmenutype']) {
            # 중고상품
            case '2' :
                $prequery['product_stats'] = '0';
                $prequery['product_sangtae'] = '1';
                break;
            # 새상품
            case '3' :
                $prequery['product_stats'] = '0';
                $prequery['product_sangtae'] = '0';
                break;
            # 판매종료
            case '5' :
                $prequery['product_stats'] = '1';
                break;
            # 파워셀러존
            case '6' :
                $prequery['id'] = marketData::getInstance()->getPowerSellerList();
                break;

            # 누적판매순
            case '7' :
                $prequery['product_stats'] = '0';
                $this->sort = '누적판매순';
                break;

            default :
                $prequery['product_stats'] = '0';
        }

        # search_type, search_word
        if ($request['search_word']) {
            # search_type이 정의된 경우
            if ($request['search_type']) {
                $prequery['search_type'] = $request['search_type'];
                $prequery['search_word'] = $request['search_word'];
                # title 검색은 product_name 검색으로 치환
                if ($prequery['search_type'] == 'title') {
                    $prequery['search_type'] = 'product_name';
                    $this->sort = '정확도순';
                }
            } # search_type이 정의되지 않은 경우
            else {
                $product_code_pattern = "/[A-Z]{2}\d{7}/i";
                preg_match($product_code_pattern, $request['search_word'], $match);
                # search_type이 지정되지 않았을때, search_word가 상품코드 패턴이면, search_type을 상품코드로 간주합니다
                if (count($match)) {
                    $prequery['product_code'] = $request['search_word'];
                } else {
                    # 판매자 ID검색일 경우
                    $sql = "SELECT COUNT(*) FROM (상품테이블) WHERE id = '{$request['search_word']}' AND product_stats = 0";
                    list($is_seller_id) = mysql_fetch_row(query($sql));
                    if ($is_seller_id > 0) {
                        $prequery['id'] = array($request['search_word']);
                    } else {
                        $prequery['search_word'] = $request['search_word'];
                        $this->sort = '정확도순';
                    }
                }
            }
        }

        # price_min, price_max
        if ($request['price_min']) $prequery['price_min'] = $request['price_min'];
        if ($request['price_max']) $prequery['price_max'] = $request['price_max'];

        $this->request = $request;
        $this->prequery = $prequery;
        return $prequery;
    }

    /**
     * requestToPrequery로 생성된 prequery 데이터를 기반으로
     * query_dsl의 query를 영역을 생성하고, 전체 query_dsl에 적용한 뒤
     * 전체 query_dsl을 배열로 반환합니다.
     * 반환된 배열은 json으로 변경되어 elasticsearch에 질의하게 됩니다.
     */
    public function prequeryToDsl($prequery = null)
    {
        /*
        [prequery 형식]
        location : (array->query:filter:terms) ['1','2'] || ['3'] || ['4']
        brand_name : (string->query:filter:term)
        category : (string->query:filter:prefix)
        model_name : (string->query:filter:term)
        product_sangtae : (string->query:filter:term) '0' || '1'
        product_stats : (string->query:filter:term) '0' || '1'
        product_code : (string->query:filter:term)
        premium : (bool->query:filter:range)
        id : (array->query:filter:terms) ['pricegolf','qudghk1219'...]
        product_number : (array->query:filter:terms)
        price_min,price_max : (int->query:filter:range)
        reg_date_min,reg_date_max : (string->query:filter:range) YMDHIS
        start_date_min,start_date_max : (string->query:filter:range) YMDHIS
        [검색처리]
        search_type : product_name|product_code|id
        search_word : (string)
        [수동 정의 필터]
        must_not : { // 수동정의
            category : (array->terms)[2247,2248]
            product_number : (array->terms)[]
        }
        [레이지로드]
        number_lt : (int->query:filter:range)
        */
        /*
        query_dsl = {
            _source : [] // SELECT
            from : (int) // LIMIT
            size : (int) // LIMIT
            sort : [{"start_date":{"order":"DESC"}]
            query : bool : filter : bool : must : [{term|terms|range|exists}]
             query : bool : filter : bool : must_not : []
             query : bool : should : []
        }
        */

        # 프리쿼리 수동입력
        if ($prequery) $this->prequery = $prequery;

        $DSL = $this->query_dsl;
        $FILTER_MUST = $FILTER_MUST_NOT = $SHOULD = array();

        # location
        if (count($this->prequery['location'])) {
            $FILTER_MUST[] = array('terms' => array('location' => $this->prequery['location']));
        }
        # brand_name
        if ($this->prequery['brand_name']) {
            $FILTER_MUST[] = array('term' => array('brand_name.exact' => $this->prequery['brand_name']));
        }
        # category
        if ($this->prequery['category']) {
            $FILTER_MUST[] = array('prefix' => array('category' => $this->prequery['category']));
        }
        # model_name
        if ($this->prequery['model_name']) {
            $FILTER_MUST[] = array('term' => array('model_name' => $this->prequery['model_name']));
        }
        # product_sangtae
        if ($this->prequery['product_sangtae'] !== null) {
            $FILTER_MUST[] = array('term' => array('product_sangtae' => $this->prequery['product_sangtae']));
        }
        # product_stats
        if ($this->prequery['product_stats'] !== null) {
            $FILTER_MUST[] = array('term' => array('product_stats' => $this->prequery['product_stats']));
        }
        # product_code
        if ($this->prequery['product_code']) {
            $FILTER_MUST[] = array('match' => array('product_code' => array('query' => $this->prequery['product_code'], 'type' => 'phrase')));
        }
        # id
        if (count($this->prequery['id'])) {
            $FILTER_MUST[] = array('terms' => array('id' => $this->prequery['id']));
        }
        # product_number
        if (count($this->prequery['product_number'])) {
            $FILTER_MUST[] = array('terms' => array('number' => $this->prequery['product_number']));
        }
        # premium
        if ($this->prequery['premium']) {
            $FILTER_MUST[] = array('range' => array('premium' => array('gte' => 'now')));
            $this->post_process_list[] = '프리미엄';
        }
        # price_min & price_max
        if ($this->prequery['price_min'] && $this->prequery['price_max']) {
            $FILTER_MUST[] = array('range' => array('baro_price' => array('gte' => $this->prequery['price_min'], 'lte' => $this->prequery['price_max'])));
        } else {
            # price_min
            if ($this->prequery['price_min']) {
                $FILTER_MUST[] = array('range' => array('baro_price' => array('gte' => $this->prequery['price_min'])));
            } # price_max
            elseif ($this->prequery['price_max']) {
                $FILTER_MUST[] = array('range' => array('baro_price' => array('lte' => $this->prequery['price_max'])));
            }
        }
        # reg_date_min & reg_date_max
        if ($this->prequery['reg_date_min'] && $this->prequery['reg_date_max']) {
            $FILTER_MUST[] = array('range' => array('reg_date' => array('gte' => ElasticsearchUtil::getDateTime($this->prequery['reg_date_min']), 'lt' => ElasticsearchUtil::getDateTime($this->prequery['reg_date_max']))));
        } else {
            # reg_date_min
            if ($this->prequery['reg_date_min']) {
                $FILTER_MUST[] = array('range' => array('reg_date' => array('gte' => ElasticsearchUtil::getDateTime($this->prequery['reg_date_min']))));
            } # reg_date_max
            elseif ($this->prequery['reg_date_max']) {
                $FILTER_MUST[] = array('range' => array('reg_date' => array('lt' => ElasticsearchUtil::getDateTime($this->prequery['reg_date_max']))));
            }
        }
        # start_date_min & start_date_max
        if ($this->prequery['start_date_min'] && $this->prequery['start_date_max']) {
            $FILTER_MUST[] = array('range' => array('start_date' => array('gte' => ElasticsearchUtil::getDateTime($this->prequery['start_date_min']), 'lt' => ElasticsearchUtil::getDateTime($this->prequery['start_date_max']))));
        } else {
            # start_date_min
            if ($this->prequery['start_date_min']) {
                $FILTER_MUST[] = array('range' => array('start_date' => array('gte' => ElasticsearchUtil::getDateTime($this->prequery['start_date_min']))));
            } # start_date_max
            elseif ($this->prequery['start_date_max']) {
                $FILTER_MUST[] = array('range' => array('start_date' => array('lt' => ElasticsearchUtil::getDateTime($this->prequery['start_date_max']))));
            }
        }
        # number_lt
        if ($this->prequery['number_lt']) {
            $FILTER_MUST[] = array('range' => array('number' => array('lt' => $this->prequery['number_lt'])));
        }


        # MUST_NOT
        # category
        if (count($this->prequery['must_not']['category'])) {
            $FILTER_MUST_NOT[] = array('terms' => array('category' => $this->prequery['must_not']['category']));
        }
        # product_number
        if (count($this->prequery['must_not']['product_number'])) {
            $FILTER_MUST_NOT[] = array('terms' => array('number' => $this->prequery['must_not']['product_number']));
        }
        if ($GLOBALS['개발자'] == false) {
            $FILTER_MUST_NOT[] = array('terms' => array('id' => array('qudghk1219', 'qudghk1219s', 'iqrash')));
        }

        # 검색어
        if ($this->prequery['search_word']) {
            switch ($this->prequery['search_type']) {
                case 'id' :
                    $FILTER_MUST[] = array('term' => array('id' => $this->prequery['search_word']));
                    break;
                case 'product_code' :
                    $FILTER_MUST[] = array('term' => array('product_code' => $this->prequery['search_word']));
                    break;
                case 'product_name' :
                default :
                    # match phrase
                    $SHOULD[] = array(
                        'match' => array(
                            'product_name' => array(
                                'query' => $this->prequery['search_word'], 'type' => 'phrase', 'boost' => 2
                            )
                        )
                    );
                    # multi_match
                    $SHOULD[] = array('multi_match' => array('query' => $this->prequery['search_word'], 'type' => 'most_fields', 'operator' => 'and',
                        'fields' => array(
                            'product_name', 'product_name.exact', 'product_name.korean', 'product_name.pricegolf', "model_name_k.exact", "model_name_k.with_synonym"
                        )));
                    break;
            }
        }

        /*
        # SHOULD_MATCH
        if( $this->prequery['match']['product_name'] )
        {
            $SHOULD_MATCH[] = array('multi_match'=>array('query'=>$this->prequery['match']['product_name'],'type'=>'cross_fields','operator'=>'and',
                'fields'=>array(
                    'product_name','product_name.exact','product_name.korean','product_name.pricegolf',"model_name_k.exact","model_name_k.with_synonym"
            )));
            $SHOULD_MATCH[] = array('wildcard'=>array('product_name.exact'=>"*{$this->prequery['match']['product_name']}*"));
        }
        if( $this->prequery['match']['id'] )
        {
            $SHOULD_MATCH[] = array('match'=>array('id'=>array('query'=>$this->prequery['match']['id'],'type'=>'phrase')));
        }
        if( count($SHOULD_MATCH) )
        {
            $DSL_QUERY_BOOL_MUST[] = array('bool'=>array('should'=>$SHOULD_MATCH));
        }
        */


        # QUERY 종료
        if (count($FILTER_MUST)) {
            $DSL['query']['bool']['filter']['bool']['must'] = $FILTER_MUST;
        }
        if (count($FILTER_MUST_NOT)) {
            $DSL['query']['bool']['filter']['bool']['must_not'] = $FILTER_MUST_NOT;
        }
        if (count($SHOULD)) {
            $DSL['query']['bool']['should'] = $SHOULD;
            $DSL['query']['bool']['minimum_should_match'] = '1';
            #$DSL['min_score'] = '0.1';
        }

        # sort
        switch ($this->sort) {
            case '랜덤' :
                $DSL['sort'][] = array('_script' => array('script' => 'Math.random()', 'type' => 'number', 'order' => 'asc'));
                break;

            case '누적판매순' :
                $DSL['sort'][] = array('sell_count' => array('order' => 'desc'));
                break;

            case '등록일순' :
                $DSL['sort'][] = array('reg_date' => array('order' => 'desc'));
                break;

            case '정확도순' :
                $DSL['sort'][] = array('_score' => array('order' => 'desc'));
                break;

            case '정렬없음' :
                break;

            # 기본값 : 시작일순
            default :
                $DSL['sort'][] = array('start_date' => array('order' => 'desc'));
        }

        # aggregations
        $AGGR = array();
        if ($this->prequery['aggregation_brand_name']) {
            $AGGR['brand_name'] = array('terms' => array('field' => 'brand_name.exact', 'size' => 0));
        }
        if ($this->prequery['aggregation_category']) {
            $AGGR['category'] = array('terms' => array('field' => 'category', 'size' => 0));
        }
        if ($this->prequery['aggregation_model_name']) {
            $AGGR['model_name'] = array('terms' => array('field' => 'model_name', 'size' => 0));
        }
        if ($this->prequery['aggregation_id']) {
            $AGGR['id'] = array('terms' => array('field' => 'id', 'size' => 0));
        }

        if (count($AGGR)) {
            $DSL['aggregations'] = $AGGR;
        }

        $this->query_dsl = $DSL;
        return $DSL;
    }


    # 집계 준비 : exec전에 실행한 뒤 get_aggregation으로 데이터를 받습니다
    # request를 기준으로 실행됩니다
    public function setAggregations()
    {
        # 브랜드 집계 : 검색어가 있을때
        if ($this->request['search_word']) {
            $this->prequery['aggregation_brand_name'] = true;
        } # 브랜드 집계 : 검색어가 없을때
        else {
            # 검색어가 없고, 브랜드 검색조건이 있을때
            if ($this->request['brand_name']) {
                # 브랜드 검색조건을 제거하여 재집계 합니다
                # 현재 브랜드 검색조건은 selected 처리 합니다
                $this->post_aggregations['brand_name'] = true;
            } # 검색어가 없고, 브랜드 검색조건이 없을때
            else {
                # 검색조건에서 브랜드를 집계합니다
                $this->prequery['aggregation_brand_name'] = true;
            }
        }

        # 카테고리 집계
        if ($this->request['type']) $last_category = $this->request['type'];
        elseif ($this->request['company']) $last_category = $this->request['company'];
        elseif ($this->request['category']) $last_category = $this->request['category'];

        # 카테고리 집계 : 분류검색이 없을때
        if ($last_category == null) {
            # 현재 검색조건에서 대분류로 출력
            $this->prequery['aggregation_category'] = 1;
        } # 카테고리 집계 : 분류검색이 있을때
        else {
            $thread = explode('r', $last_category);
            $thread_count = count($thread);
            switch ($thread_count) {
                # 카테고리 집계 : 대분류 검색시
                case 1 :
                    # 현재 검색조건에서 중분류로 출력
                    $this->prequery['aggregation_category'] = 2;
                    break;
                # 카테고리 집계 : 중분류 검색시
                case 2 :
                    # 자식 카테고리 유무 확인
                    $category_list = marketData::getInstance()->getCategoryList();
                    $search_category = util::searchByKeyValue($category_list, 'thread', $last_category, 1);
                    # 자식 카테고리가 있으면
                    if ($search_category[0]['hasChildren']) {
                        # 현재 검색조건에서 소분류로 출력
                        $this->prequery['aggregation_category'] = 3;
                    } # 자식 카테고리가 없으면
                    else {
                        # 대분류로 검색하여 중분류로 출력
                        $this->post_aggregations['category'] = array('prefix' => "{$thread[0]}r", 'group_level' => 2);
                    }
                    break;
                # 카테고리 집계 : 소분류 검색시
                case 3 :
                    # 중분류로 검색하여 소분류로 출력
                    $this->post_aggregations['category'] = array('prefix' => "{$thread[0]}r{$thread[1]}", 'group_level' => 3);
                    break;
            }
        }

        # 모델 집계 : 브랜드와 대분류가 지정되어 있을때에만 집계
        if ($this->request['brand_name'] && $last_category) {
            # 모델 검색이 있으면
            if ($this->request['model_name']) {
                # 모델 검색을 제외하고 재집계
                $this->post_aggregations['model_name'] = true;
            } # 모델 검색이 없으면
            else {
                # 모델 집계을 포함하여 검색
                $this->prequery['aggregation_model_name'] = true;
            }
        }
    }


    # STEP3 : query_dsl을 질의하고 후처리한 뒤 검색된 상품 목록을 반환합니다
    public function exec($query_dsl = null)
    {
        # query_dsl to json
        if ($query_dsl) $this->query_dsl = $query_dsl;
        if ($this->query_dsl == null) return false;
        $this->query_dsl_json = util::array_to_json($this->query_dsl);

        # ElasticSearch
        $es = Elasticsearch::getInstance('_search', $this->index);
        $this->result_json = $es->exec($this->query_dsl_json);
        $this->result_array = util::json_to_array($this->result_json);

        if ($_GET['show_query_dsl']) {
            echoDev($this->request, $this->prequery, $this->query_dsl_json);
        }
        if ($_GET['show_instance']) {
            echoDev($this);
        }

        $this->product_list = array();
        $this->product_list_total = $this->result_array['hits']['total'];
        for ($i = 0, $this->product_list_count = count($this->result_array['hits']['hits']); $i < $this->product_list_count; ++$i) {
            $this->product_list[$i] = $this->result_array['hits']['hits'][$i]['_source'];
        }

        # 후처리 조건
        for ($i = 0, $end = count($this->post_process_list); $i < $end; ++$i) {
            $PP[$this->post_process_list[$i]] = true;
        }
        if ($PP['대분류명']) {
            $inst_MD = marketData::getInstance();
        }
        if ($PP['시작일']) {
            $now_ymd = date('Ymd');
            $now_time = time();
        }
        if ($PP['검색어강조']) {
            if ($this->prequery['search_word']) {
                $analyzed_search_word = stringAnalyze($this->prequery['search_word']);
                $PP['검색어강조'] = count($analyzed_search_word);
            } else {
                $PP['검색어강조'] = false;
            }
        }
        if ($PP['판매자정보']) {
            $top3_power_seller = marketData::getInstance()->top3_power_seller();
            $top3_power_seller_id_list = util::array_column($top3_power_seller, 'id');
        }

        # 후처리 실행
        for ($i = 0; $i < $this->product_list_count; ++$i) {
            $row = &$this->product_list[$i];
            if ($PP['상품주소']) {
                $row['href'] = "/view.php?nowlocation={$_GET['nowlocation']}&num={$row['number']}&category={$row['category']}";
            }
            if ($PP['즉시구매가']) {
                $row['susuryo'] = $this->getSellerData($row['id'], 'susuryo');
                calBaroPrice($row);
            }
            if ($PP['판매자정보']) {
                $row['trust_point'] = $this->getSellerData($row['id'], 'trust_point');
                $row['credit_icon_seller'] = $this->getSellerData($row['id'], 'credit_icon_seller');
                $row['power_seller'] = $this->getSellerData($row['id'], 'power_seller');
                $row['user_nick'] = $this->getSellerData($row['id'], 'user_nick');
                $row['kin_sum'] = $this->getSellerData($row['id'], 'kin_sum');

                if ($row['power_seller']) {
                    $row['trust_icon'] = null;
                    $row['credit_icon_seller'] = '/img/icon_power.gif';
                    $row['credit_icon_seller_text'] = '파워셀러';
                } else {
                    list($row['trust_icon_text'], $row['trust_icon']) = self::getSellerTrustIcon($row['trust_point']);
                    if (!$row['credit_icon_seller']) $row['credit_icon_seller'] = 'upload/credit_icon/1369130751-2417.gif';
                    $row['credit_icon_seller'] = '/' . $row['credit_icon_seller'];
                    switch ($row['credit_icon_seller']) {
                        case '/upload/credit_icon/1369130751-2417.gif' :
                            $row['credit_icon_seller_text'] = '입문';
                        case '/upload/credit_icon/1369130771-4534.gif' :
                            $row['credit_icon_seller_text'] = '초보';
                        case '/upload/credit_icon/1370597781-2818.gif' :
                            $row['credit_icon_seller_text'] = '일반';
                        case '/upload/credit_icon/1370597790-2517.gif' :
                            $row['credit_icon_seller_text'] = '우수';
                        case '/upload/credit_icon/1370597798-8080.gif' :
                            $row['credit_icon_seller_text'] = '최우수';
                    }
                }

                # top3 판매자 랭크 아이콘 추가
                if (in_array($row['id'], $top3_power_seller_id_list)) {
                    list($top3_row) = util::searchByKeyValue($top3_power_seller, 'id', $row['id'], 1);
                    $row['rank_icon'] = $top3_row['rank_icon'];
                    $row['rank'] = $top3_row['rank'];
                }
            }
            if ($PP['시작일']) {
                $row['start_time'] = strtotime($row['start_date']);
                $row['start_date'] = date('Y-m-d H:i:s', $row['start_time']);
                $diff_time = $now_time - $row['start_time'];

                # 1분이내 : new
                if ($diff_time < 60) {
                    $row['start_time_html'] = '<img src="/img/new.gif" alt="new">';
                } # 10분 이내 : m 분전
                elseif ($diff_time < 600) {
                    $passed_minute = floor($diff_time / 60);
                    $row['start_time_html'] = "<span style='color:#00c;'>{$passed_minute} 분전</span>";
                } # 오늘
                elseif ($now_ymd == date('Ymd', $row['start_time'])) {
                    $row['start_time_html'] = date('m-d H:i', $row['start_time']);
                } else {
                    $row['start_time_html'] = date('y-m-d', $row['start_time']);
                }
            }
            if ($PP['등록일']) {
                $row['reg_time'] = strtotime($row['reg_date']);
                $row['reg_date'] = date('Y-m-d H:i:s', $row['reg_time']);
            }
            if ($PP['배송비']) {
                switch ($row['baesong_type']) {
                    case '0' :
                        $row['shipping_fee_text'] = '무료배송';
                        break;
                    case '1' :
                        $row['shipping_fee_text'] = '착불';
                        break;
                    case '2' :
                        $row['shipping_fee_text'] = '착불';
                        break;
                    case '3' :
                        if ($row['baesong_cut_free'] < $row['baro_price'])
                            $row['shipping_fee_text'] = '무료배송';
                        else
                            $row['shipping_fee_text'] = '조건부무료';
                        break;
                }
            }
            if ($PP['대분류명']) {
                list($category_lv1) = explode('r', $row['category']);
                list($row['category_name']) = $inst_MD->getCategoryTitle($category_lv1);
            }
            if ($PP['사용기간']) {
                if ($row['product_sangtae'] == '1') {
                    if ($row['product_use_time_month'] > 0) $row['use_time_text'] = sprintf('%02d개월', $row['product_use_time_month']);
                    else $row['use_time_text'] = '미사용';
                } else {
                    $row['use_time_text'] = '새상품';
                }
            }
            if ($PP['정품여부']) {
                switch ($row['derive']) {
                    case 1 :
                        $row['derive_text'] = "아시안 스펙 정품";
                        break;
                    case 2 :
                        $row['derive_text'] = "아시안 스펙 직수입(병행수입)";
                        break;
                    case 3 :
                        $row['derive_text'] = "미국 스펙 정품";
                        break;
                    case 4 :
                        $row['derive_text'] = "미국 스펙 직수입(병행수입)";
                        break;
                    case 5 :
                        $row['derive_text'] = "피팅클럽";
                        break;
                    default :
                        $row['derive_text'] = "정확히 모름";
                        break;
                }
            }
            if ($PP['누적판매순위']) {
                $row['accum_sell_count_rank'] = $this->query_dsl['from'] + $i + 1;
            }
            if ($PP['검색어강조']) {
                if ($PP['안드로이드앱']) {
                    $row['product_name_highlight'] = stringHighlightWord($row['product_name'], $analyzed_search_word, "<font color='#66cc66'><b>", "</b></font>");
                } else {
                    $row['product_name_highlight'] = stringHighlightWord($row['product_name'], $analyzed_search_word, "<strong class='searched'>", "</strong>");
                }
            } elseif ($PP['안드로이드앱']) {
                $row['product_name_highlight'] = $row['product_name'];
            }
            if ($PP['섬네일']) {
                if ($row['img1'][0] == '/') $row['img1'] = substr($row['img1'], 1);
                $this->thumbnail_option['file'] = "{$_SERVER['DOCUMENT_ROOT']}/wys2/file_attach/{$row['img1']}";
                $row['thumb'] = self::getThumbnail($this->thumbnail_option);
                $row['thumb'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', $row['thumb']);
                unset($this->thumbnail_option['file']);
            }
            if ($PP['프리미엄']) {
                $row['show_premium_icon'] = true;
            }
            if ($PP['안드로이드앱']) {
                # 섬네일 키 변경
                $row['thumb_img_path'] = $row['thumb'];
                unset($row['thumb']);
                # 정품여부에서 (병행수입) 제거 및 키 변경
                $row['derive'] = str_replace('(병행수입)', '', $row['derive_text']);
                unset($row['derive_text']);
                # 판매자 데이터 추가
                $row['credit_icon_seller'] = $this->getSellerData($row['id'], 'credit_icon_seller');
                $row['credit_level_seller'] = $this->getSellerData($row['id'], 'credit_level_seller');
                $row['credit_point_seller'] = $this->getSellerData($row['id'], 'credit_point_seller');
                $row['credit_icon_buyer'] = $this->getSellerData($row['id'], 'credit_icon_buyer');
                $row['credit_level_buyer'] = $this->getSellerData($row['id'], 'credit_level_buyer');
                $row['credit_point_buyer'] = $this->getSellerData($row['id'], 'credit_point_buyer');
                $row['credit_price_seller'] = $this->getSellerData($row['id'], 'credit_price_seller');
                $row['rating_good'] = $this->getSellerData($row['id'], 'rating_good');
                $row['rating_soso'] = $this->getSellerData($row['id'], 'rating_soso');
                $row['rating_bad'] = $this->getSellerData($row['id'], 'rating_bad');
                $row['option_value'] = $row['susuryo'];
                # 이미지 더미 데이터 추가
                $row['img2'] = $row['img3'] = $row['img4'] =
                $row['img5'] = $row['img6'] = $row['img7'] =
                $row['img8'] = $row['img9'] = $row['img10'] =
                $row['etc_option'] = '';
            }
        }


        # DSL과 동시집계된 데이터 보관
        if ($this->result_array['aggregations']) {
            # 브랜드 집계
            if ($this->result_array['aggregations']['brand_name'])
                $this->result_aggregations['brand_name'] = $this->result_array['aggregations']['brand_name']['buckets'];
            # 모델 집계
            if ($this->result_array['aggregations']['model_name'])
                $this->result_aggregations['model_name'] = $this->result_array['aggregations']['model_name']['buckets'];
            # 카테고리 집계
            if ($this->result_array['aggregations']['category'])
                $this->result_aggregations['category'] = $this->categoryAggregationDataMerge($this->result_array['aggregations']['category']['buckets'], $this->prequery['aggregation_category']);
            # ID 집계
            if ($this->result_array['aggregations']['id'])
                $this->result_aggregations['id'] = $this->result_array['aggregations']['id']['buckets'];
        }
        unset($this->result_array['aggregations']);

        return $this->product_list;
    }

    # exec_test
    public function execTest($query_dsl = null)
    {
        # query_dsl to json
        if ($query_dsl) $this->query_dsl = $query_dsl;
        if ($this->query_dsl == null) return false;
        $this->query_dsl_json = util::array_to_json($this->query_dsl);

        # ElasticSearch
        $es = Elasticsearch::getInstance('_search', $this->index);
        $this->result_json = $es->exec($this->query_dsl_json);
        $this->result_array = util::json_to_array($this->result_json);
        return $this->result_array;
    }

    public function getAggregationsData()
    {
        # 브랜드 후집계
        if ($this->post_aggregations['brand_name']) {
            # 브랜드 검색조건을 제외한 DSL 추출
            $temp_self = new self;
            $temp_request = $this->request;
            unset($temp_request['brand_name']);
            $temp_self->requestToPrequery($temp_request);
            $temp_dsl = $temp_self->prequeryToDsl();
            # 집계 DSL 작성
            $this->dsl_post_aggregation['brand_name'] = array(
                'size' => 0,
                'query' => $temp_dsl['query'],
                'aggregations' => array('brand_name' => array('terms' => array('field' => 'brand_name.exact', 'size' => 0)))
            );
            if ($_GET['show_query_dsl']) {
                echoDev(util::array_to_json($this->dsl_post_aggregation['brand_name']));
            }
            # CURL 실행
            $es = Elasticsearch::getInstance('_search', $this->index);
            $aggregation_result = util::json_to_array($es->exec(util::array_to_json($this->dsl_post_aggregation['brand_name'])));
            $this->result_aggregations['brand_name'] = $aggregation_result['aggregations']['brand_name']['buckets'];
        }
        $this->result_aggregations['brand_name'] = $this->sortBrandNameAggregation($this->result_aggregations['brand_name']);

        # 카테고리 후집계
        if ($this->post_aggregations['category']) {
            # 카테고리 검색조건을 변경한 DSL 추출
            $temp_self = new self;
            $temp_request = $this->request;
            unset($temp_request['category'], $temp_request['company'], $temp_request['type']);
            $thread = explode('r', $this->post_aggregations['category']['prefix']);
            # 대분류로 검색하여 중분류 목록 노출
            if ($this->post_aggregations['category']['group_level'] == 2) {
                $temp_request['category'] = $thread[0];
            } # 중분류로 검색하여 대분류 목록 노출
            elseif ($this->post_aggregations['category']['group_level'] == 3) {
                $temp_request['category'] = $thread[0];
                $temp_request['company'] = "{$thread[0]}r{$thread[1]}";
            }
            $temp_self->requestToPrequery($temp_request);
            $temp_dsl = $temp_self->prequeryToDsl();
            # 집계 DSL 작성
            $this->dsl_post_aggregation['category'] = array(
                'size' => 0,
                'query' => $temp_dsl['query'],
                'aggregations' => array('category' => array('terms' => array('field' => 'category', 'size' => 0)))
            );
            if ($_GET['show_query_dsl']) {
                echoDev(util::array_to_json($this->dsl_post_aggregation['category']));
            }
            # CURL 실행
            $es = Elasticsearch::getInstance('_search', $this->index);
            $aggregation_result = util::json_to_array($es->exec(util::array_to_json($this->dsl_post_aggregation['category'])));
            # group_level로 취합 후 적용
            $this->result_aggregations['category'] = $this->categoryAggregationDataMerge($aggregation_result['aggregations']['category']['buckets'], $this->post_aggregations['category']['group_level']);
        }

        # 모델 후집계
        if ($this->post_aggregations['model_name']) {
            $temp_self = new self;
            $temp_request = $this->request;
            unset($temp_request['model_name']);
            $temp_self->requestToPrequery($temp_request);
            $temp_dsl = $temp_self->prequeryToDsl();
            # 집계 DSL 작성
            $this->dsl_post_aggregation['model_name'] = array(
                'size' => 0,
                'query' => $temp_dsl['query'],
                'aggregations' => array('model_name' => array('terms' => array('field' => 'model_name', 'size' => 0)))
            );
            if ($_GET['show_query_dsl']) {
                echoDev(util::array_to_json($this->dsl_post_aggregation['brand_name']));
            }
            # CURL 실행
            $es = Elasticsearch::getInstance('_search', $this->index);
            $aggregation_result = util::json_to_array($es->exec(util::array_to_json($this->dsl_post_aggregation['model_name'])));
            $this->result_aggregations['model_name'] = $aggregation_result['aggregations']['model_name']['buckets'];
        }

        # 모델 집계 후처리
        if ($this->result_aggregations['model_name']) {
            $model_sub_number_list = array();
            for ($i = 0, $end = count($this->result_aggregations['model_name']); $i < $end; ++$i) {
                if ($this->result_aggregations['model_name'][$i]['key']) {
                    $model_sub_number_list[] = $this->result_aggregations['model_name'][$i]['key'];
                }
            }
            if (count($model_sub_number_list)) {
                # 모델명 추출
                $model_sub_number_list_imploded = implode(',', $model_sub_number_list);
                $sql_model = "SELECT model_sub_num, model_name FROM (모델테이블) WHERE model_sub_num IN ({$model_sub_number_list_imploded})";
                $model_data = util::query_to_array($sql_model);
                $model_aggregation_list = array();
                for ($i = 0; $i < $end; ++$i) {
                    if ($this->result_aggregations['model_name'][$i]['key'] == false) continue;
                    $model_data_row = current(util::searchByKeyValue($model_data, 'model_sub_num', $this->result_aggregations['model_name'][$i]['key'], 1));
                    if ($model_data_row == false) contienu;

                    $model_aggregation_list[] = array(
                        'model_name' => $model_data_row['model_name'],
                        'model_sub_num' => $model_data_row['model_sub_num'],
                        'count' => $this->result_aggregations['model_name'][$i]['doc_count']
                    );
                }
                $this->result_aggregations['model_name'] = $model_aggregation_list;
            } else {
                unset($this->result_aggregations['model_name']);
            }
        }

        return $this->result_aggregations;
    }

    public function categoryAggregationDataMerge($data, $group_level)
    {
        $result = array();
        for ($i = 0, $end = count($data); $i < $end; ++$i) {
            $row = &$data[$i];
            $thread = explode('r', $row['key']);
            if (count($thread) > $group_level) {
                $merge_key = array();
                for ($j = 0; $j < $group_level; ++$j) {
                    $merge_key[] = $thread[$j];
                }
                $merge_key = implode('r', $merge_key);
            } else $merge_key = $row['key'];
            if (isset($result[$merge_key]) == false) $result[$merge_key] = 0;
            $result[$merge_key] += $row['doc_count'];
        }
        return $result;
    }

    public function sortBrandNameAggregation($data)
    {
        $sort_result = array();
        $brand_list = marketData::getInstance()->getBrandList();
        $_REQUEST['nowlocation'] = $_GET['nowlocation'];
        $temp_qs = util::getQueryString('nowlocation,category,company,type,search_type,search_word,selectedtabmenutype,main_pagescale,ag_id');
        for ($i = 0, $end = count($brand_list); $i < $end; ++$i) {
            $aggr_row = util::searchByKeyValue($data, 'key', $brand_list[$i]['brand_name'], 1);
            if (count($aggr_row)) {
                $brand_list[$i]['count'] = $aggr_row[0]['doc_count'];
                $brand_list[$i]['qs'] = $temp_qs . '&brand_name=' . $brand_list[$i]['brand_name'];
                #if( $brand_list[$i]['brand_name'] == $this->request['brand_name'] ) $brand_list[$i]['main_disp'] = 'Y';
                $sort_result[] = $brand_list[$i];
            }
        }
        return $sort_result;
    }


    # 페이지네이션 생성
    public function getPaginationHtml()
    {
        $link = $_SERVER['PHP_SELF'] . "?" . util::getQueryStringException('page,sort_how');
        return util::pagination_to_html(util::pagination($this->product_list_total, $this->size, $this->page), $link);
    }

    # 판매자 정보 추출
    public function getSellerData($id, $column)
    {
        if (count($this->seller_data)) return $this->seller_data[$id][$column];
        else {
            # 판매자 목록 추출
            $seller_list = array();
            for ($i = 0; $i < $this->product_list_count; ++$i) {
                $seller_list[] = "'" . $this->product_list[$i]['id'] . "'";
            }
            $seller_list = implode(',', array_unique($seller_list));

            $SQL = "SELECT " . implode(',', $this->sql_seller['SELECT']);
            $SQL .= " FROM (회원테이블) AS HM";
            $SQL .= " WHERE HM.user_id IN ({$seller_list})";
            $result = query($SQL);

            while ($row = mysql_fetch_assoc($result)) {
                $this->seller_data[$row['user_id']] = $row;
            }
            return $this->seller_data[$id][$column];
        }
    }

    /**
     * 섬네일 주소를 반환합니다
     * $args
     *    - file
     *    - width
     *    - height
     */
    static function getThumbnail($args)
    {
        try {
            /*if( $_COOKIE['ad_id'] == 'qudghk1219' )
            {
                $url = 'http://image.pricegolf.co.kr/get.php?';
                $param = array();
                $param['file'] = str_replace("{$_SERVER['DOCUMENT_ROOT']}/wys2/file_attach/",'',$args['file']);
                $param['size'] = $args['height'];
                $url .= http_build_query($param);
                return $url;
            }*/

            # 경로정보 추출
            $pathinfo = util::pathinfo($args['file']);

            # 필수 인수 검사
            if (!is_file($pathinfo['server_fullname'])) throw new Exception();
            if (!$args['width'] && !$args['height']) throw new Exception();
            elseif (!$args['width']) $args['width'] = $args['height'];
            elseif (!$args['height']) $args['height'] = $args['width'];

            #썸네일이미지파일명
            #{원본파일}_{N(로고고정)}_{7(로고위치고정)}_{가로}x{세로}_{'100'(퀄리티고정)}_{'2'(비율대로확대고정)}.확장자
            $thumb['server_dirname'] = str_replace('file_attach', 'file_attach_thumb', $pathinfo['server_dirname']);
            $thumb['basename'] = "{$pathinfo['filename']}_N_7_{$args['width']}x{$args['height']}_100_2.{$pathinfo['extension']}";
            $thumb['server_fullname'] = "{$thumb['server_dirname']}/{$thumb['basename']}";
            $thumb = util::pathinfo($thumb['server_fullname']);

            # 파일 확인 및 생성
            if (!is_file($thumb['server_fullname']))
                imageUploadNew($pathinfo['server_fullname'], $thumb['server_fullname'], $args['width'], $args['height'], 100, '비율대로확대', 2);

            # 웹 절대경로 반환
            return $thumb['web_fullname'];
        } catch (Exception $e) {
            return '/img/no_photo.gif';
        }
    }

    # 신용도 점수로 아이콘 반환
    static function getSellerTrustIcon($trustPoint = 0)
    {
        global $CONF;

        if (!is_numeric($trustPoint) || $trustPoint < $CONF['bron_conf']) {
            $return = array('새싹', '/img/icon_trust_1.gif');
        } elseif ($trustPoint < $CONF['silver_conf']) {
            $return = array('브론즈', '/img/icon_trust_2.gif');
        } elseif ($trustPoint < $CONF['gold_conf']) {
            $return = array('실버', '/img/icon_trust_3.gif');
        } elseif ($trustPoint < $CONF['sapa_conf']) {
            $return = array('골드', '/img/icon_trust_4.gif');
        } elseif ($trustPoint < $CONF['dia_conf']) {
            $return = array('BEST', '/img/icon_trust_5.gif');

        } elseif ($trustPoint < $CONF['vip_conf']) {
            $return = array('TOP', '/img/icon_trust_6.gif');
        } else {
            $return = array('VIP', '/img/icon_trust_7.gif');
        }

        return $return;
    }


    # 검색 빵조각 메뉴
    public function getSearchBreadCrumb()
    {
        # 빵조각 메뉴 데이터
        $breadcrumb = array();
        # querystring 누적적용( nowlocation > brand_name > category > (모델테이블) );
        $qs_accum = array();

        # location
        switch ($this->request['nowlocation']) {
            case '2' :
                $location_text = '가맹점중고마켓';
                $qs_accum[] = 'nowlocation=2';
                break;
            case '3' :
                $location_text = '신상품할인샵';
                $qs_accum[] = 'nowlocation=3';
                break;
            default :
                $location_text = '에스크로마켓';
                $qs_accum[] = 'nowlocation=1';
                break;
        }
        $breadcrumb[] = array('type' => 'nowlocation', 'text' => $location_text, 'qs' => implode('&', $qs_accum));

        if ($this->request['nowlocation'] == '2' && $this->request['ag_id']) {
            $agency_list = marketData::getInstance()->getAgencyList();
            list($agency_info) = util::searchByKeyValue($agency_list, 'user_id', $this->request['ag_id'], 1);
            if ($agency_info) {
                $qs_accum[] = "ag_id={$this->request['ag_id']}";
                $breadcrumb[] = array('type' => 'ag_id', 'text' => $agency_info['user_nick'], 'qs' => implode('&', $qs_accum));
            }
        }

        # search_word
        if ($this->request['search_word']) {
            $qs_accum[] = "search_word={$this->request['search_word']}";
            $breadcrumb[] = array(type => 'search_word', 'text' => $this->request['search_word'], 'qs' => implode('&', $qs_accum));
        }

        # brand_name
        if ($this->request['brand_name']) {
            $qs_accum[] = "brand_name={$this->request['brand_name']}";
            $breadcrumb[] = array('type' => 'brand_name', 'text' => $this->request['brand_name'], 'qs' => implode('&', $qs_accum));
        }

        # category
        if ($this->request['type']) $last_category = $this->request['type'];
        elseif ($this->request['company']) $last_category = $this->request['company'];
        elseif ($this->request['category']) $last_category = $this->request['category'];

        if ($last_category) {
            $thread = explode('r', $last_category);
            if ($thread[1]) $thread[1] = "{$thread[0]}r{$thread[1]}";
            if ($thread[2]) $thread[2] = "{$thread[1]}r{$thread[2]}";
            for ($i = 0, $end = count($thread); $i < $end; ++$i) {
                $category_text = marketData::getInstance()->getCategoryTitle($thread[$i]);
                $category_text = end($category_text);
                switch ($i) {
                    case 0 :
                        $category_key = 'category';
                        break;
                    case 1 :
                        $category_key = 'company';
                        break;
                    case 2 :
                        $category_key = 'type';
                        break;
                }
                $qs_accum[] = "{$category_key}=$thread[$i]";
                $breadcrumb[] = array('type' => $category_key, 'text' => $category_text, 'qs' => implode('&', $qs_accum));
            }
        }

        # model
        if ($this->request['model_name']) {
            list($model_name) = mysql_fetch_row(query("SELECT model_name FROM (모델테이블) WHERE model_sub_num='{$this->request['model_name']}'"));
            $qs_accum[] = "model_name={$this->request['model_name']}";
            $breadcrumb[] = array('text' => $model_name, 'qs' => implode('&', $qs_accum));
        }

        return $breadcrumb;
    }

}