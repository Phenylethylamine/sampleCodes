<?php
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.php");

/**
 * 단건 주문 처리
 * 발주확인/발송/취소승인/교환처리/반품처리
 */
class st11_order extends st11
{
    public function __construct($jangproduct_number)
    {
        $this->jangproduct_number = $jangproduct_number;
    }

    /**
     * 자사 주문 데이터 반환
     */
    public function get_order_data()
    {
        if (isset($this->order_data)) return $this->order_data;
        $sql = "
			SELECT		*
			FROM		(주문테이블) AS A
			WHERE		A.number = '{$this->jangproduct_number}'
		";
        list($this->order_data) = util::query_to_array($sql);
        return $this->order_data;
    }

    /**
     * 발주확인처리
     */
    public function reqpackaging()
    {
        $method = 'GET';
        $order = $this->get_order_data();

        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $addPrdYn = 'N';
        $addPrdNo = 'null';
        $dlvNo = $order['coop_dlvNo'];
        $url = "https://api.11st.co.kr/rest/ordservices/reqpackaging/{$ordNo}/{$ordPrdSeq}/{$addPrdYn}/{$addPrdNo}/{$dlvNo}";

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '발주확인';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 발주확인실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = var_export($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 발주확인실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = $response;
            $return = false;
        }
        util::insert_array('coop_api_log', $log);
        return $return;
    }

    /**
     * 발주확인처리-수동
     * param
     *  ordNo
     *  ordPrdSeq
     *  addPrdYn
     *  dlvNo
     *  product_number
     */
    static function reqpackagingManual($param)
    {
        $method = 'GET';

        $ordNo = $param['ordNo'];
        $ordPrdSeq = $param['ordPrdSeq'];
        $addPrdYn = $param['addPrdYn'] ? $param['addPrdYn'] : 'N'; # 추가 구성 상품 여부
        $addPrdNo = 'null';
        $dlvNo = $param['dlvNo'];
        $url = "https://api.11st.co.kr/rest/ordservices/reqpackaging/{$ordNo}/{$ordPrdSeq}/{$addPrdYn}/{$addPrdNo}/{$dlvNo}";

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '발주확인';
        $log['jp_number'] = $param['product_number'];
        $log['ord_no'] = $param['ordNo'];
        $log['ord_seq'] = $param['ordPrdSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 발주확인실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = var_export($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 발주확인실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = $response;
            $return = false;
        }
        util::insert_array('coop_api_log', $log);
        return $return;
    }

    /**
     * 발송처리 : (주문테이블) 참조버전
     */
    public function reqdelivery()
    {
        # 부분발송처리로 실행합니다
        return $this->reqdelivery_part();

        $order = $this->get_order_data();

        $sendDt = date('YmdHi');

        switch ($order['baesong_company']) {
            case '퀵서비스' :
                $dlvMthdCd = '04';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '직접전달' :
                $dlvMthdCd = '03';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '기타발송' :
                $dlvMthdCd = '01';
                $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
            default :
                $dlvMthdCd = '01';
                $inst = new courier();
                $dlvEtprsCd = $inst->get_courier_code($order['baesong_company'], '11st');
                if ($dlvEtprsCd == '') $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                # 건영택배는 송장출력년월일+송장번호로 입력해야합니다
                if ($dlvEtprsCd == '00037') $invcNo = date('Ymd', strtotime($order['baesong_date'])) . $invcNo;
                break;
        }
        $dlvNo = $order['coop_dlvNo'];

        $method = 'GET';
        $url = "https://api.11st.co.kr/rest/ordservices/reqdelivery/{$sendDt}/{$dlvMthdCd}/{$dlvEtprsCd}/{$invcNo}/{$dlvNo}";

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '발송';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 취소처리중
        elseif ($response_array['result_code'] == '-3313') {
            return $this->cancelreqreject();
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    /**
     * 부분발송처리
     */
    public function reqdelivery_part()
    {
        $order = $this->get_order_data();

        $sendDt = date('YmdHi', strtotime($order['baesong_date']));

        switch ($order['baesong_company']) {
            case '퀵서비스' :
                $dlvMthdCd = '04';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '직접전달' :
                $dlvMthdCd = '03';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '기타발송' :
                $dlvMthdCd = '01';
                $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
            default :
                $dlvMthdCd = '01';
                $inst = new courier();
                $dlvEtprsCd = $inst->get_courier_code($order['baesong_company'], '11st');
                if ($dlvEtprsCd == '') $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
        }
        $dlvNo = $order['coop_dlvNo'];

        $partDlvYn = 'Y';
        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];

        $method = 'GET';
        $url = "https://api.11st.co.kr/rest/ordservices/reqdelivery/{$sendDt}/{$dlvMthdCd}/{$dlvEtprsCd}/{$invcNo}/{$dlvNo}/{$partDlvYn}/{$ordNo}/{$ordPrdSeq}";

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '발송';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 취소처리중
        elseif ($response_array['result_code'] == '-3313') {
            return $this->cancelreqreject();
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    /**
     * 판매거부처리
     */
    public function reqrejectorder()
    {
        $order = $this->get_order_data();

        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        # 일단은 기타로 처리.. 나중에 세분화 예정
        $ordCnRsnCd = '99';
        $exploded_memo_cancel = explode('<divider>', $order['memo_cancel']);
        $memo_cancel = array();
        for ($i = 1, $end = count($exploded_memo_cancel); $i < $end; ++$i) {
            if ($exploded_memo_cancel[$i] != '') $memo_cancel[] = $exploded_memo_cancel[$i];
        }
        $memo_cancel = implode(',', $memo_cancel);
        $memo_cancel = str_replace('/', '', $memo_cancel);
        $ordCnDtlsRsn = urlencode(iconv('euckr', 'utf8', $memo_cancel));
        $url = "https://api.11st.co.kr/rest/claimservice/reqrejectorder/{$ordNo}/{$ordPrdSeq}/{$ordCnRsnCd}/{$ordCnDtlsRsn}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '판매거부';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $CPS = array();
            $CPS['product_number'] = $order['product_number'];
            $CPS['coop_name'] = '11st';
            $CPS['reg_date'] = date('Y-m-d H:i:s');
            util::insert_array('coop_product_scheduler', $CPS);

            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = var_export($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    /**
     * 구매취소 승인
     */
    public function cancelreqconf()
    {
        $st11_order = $this->get_11st_order_stats();
        # 11번가 취소상태가 아니면 승인하지 않습니다
        if ($st11_order['ordPrdStat'] != '701') return false;

        $order = $this->get_order_data();

        $ordPrdCnSeq = $order['coop_clmNo'];
        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $url = "http://api.11st.co.kr/rest/claimservice/cancelreqconf/{$ordPrdCnSeq}/{$ordNo}/{$ordPrdSeq}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '구매취소승인';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $CPS = array();
            $CPS['product_number'] = $order['product_number'];
            $CPS['coop_name'] = '11st';
            $CPS['reg_date'] = date('Y-m-d H:i:s');
            util::insert_array('coop_product_scheduler', $CPS);

            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    /**
     * 구매취소거부 및 발송처리
     * 이미 배송한 경우 사용합니다
     */
    public function cancelreqreject()
    {
        $order = $this->get_order_data();

        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $ordPrdCnSeq = $order['coop_clmNo'];

        switch ($order['baesong_company']) {
            case '퀵서비스' :
                $dlvMthdCd = '04';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '직접전달' :
                $dlvMthdCd = '03';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '기타발송' :
                $dlvMthdCd = '01';
                $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
            default :
                $dlvMthdCd = '01';
                $inst = new courier();
                $dlvEtprsCd = $inst->get_courier_code($order['baesong_company'], '11st');
                if ($dlvEtprsCd == '') $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
        }
        $sendDt = date('Ymd', strtotime($order['baesong_date']));


        $url = "http://api.11st.co.kr/rest/claimservice/cancelreqreject/{$ordNo}/{$ordPrdSeq}/{$ordPrdCnSeq}/{$dlvMthdCd}/{$sendDt}/{$dlvEtprsCd}/{$invcNo}";
        echoDev($url);
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '구매취소거부발송';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    /**
     * 구매취소거부2
     * 판매자에게 문제가 없는데에도
     * 구매자가 '판매자 귀책'으로 취소 신청을 한 경우
     * 이 기능을 이용해 취소거부를 할 수 있습니다.
     * 판매자 귀책으로 구매취소가 일어나면 판매자 신용도 점수가 하락합니다
     */
    public function cancelreqrejectNEW()
    {
        $order = $this->get_order_data();

        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $ordPrdCnSeq = $order['coop_clmNo'];

        switch ($order['baesong_company']) {
            case '퀵서비스' :
                $dlvMthdCd = '04';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '직접전달' :
                $dlvMthdCd = '03';
                $dlvEtprsCd = 'null';
                $invcNo = 'null';
                break;
            case '기타발송' :
                $dlvMthdCd = '01';
                $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
            default :
                $dlvMthdCd = '01';
                $inst = new courier();
                $dlvEtprsCd = $inst->get_courier_code($order['baesong_company'], '11st');
                if ($dlvEtprsCd == '') $dlvEtprsCd = '00099';
                $invcNo = $order['songjang'] ? $order['songjang'] : '999';
                break;
        }
        $sendDt = date('Ymd', strtotime($order['baesong_date']));

        # 취소요청불가코드 01(취소책임사유 입력 오류) 02(상품 발송처리 완료)
        $ordCnRefsRsnCd = '01';
        # 취소요청불가사유 : 데이터 준비 안됨
        $ordCnReqRsn = '999';

        $url = "http://api.11st.co.kr/rest/claimservice/cancelreqrejectNEW/{$ordNo}/{$ordPrdSeq}/{$ordPrdCnSeq}/{$dlvMthdCd}/{$sendDt}/{$dlvEtprsCd}/{$invcNo}/{$ordCnRefsRsnCd}/{$ordCnReqRsn}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '구매취소거부발송(책임사유오류)';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = var_export($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = var_export($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    /**
     * 교환승인처리
     */
    public function exchangereqconf()
    {
        $order = $this->get_order_data();
        # 11st 클레임번호
        $clmReqSeq = $order['coop_clmNo'];
        # 11st 주문번호
        $ordNo = $order['coop_ordNo'];
        # 11st 주문순번
        $ordPrdSeq = $order['coop_ordSeq'];

        # 재배송 데이터 확인
        $sql = "
			SELECT		*
			FROM		auction_jangproduct_claim_maesong
			WHERE		`mode` = 2
			AND			`jp_number` = '{$order['number']}'
			ORDER BY	number DESC
			LIMIT		1
		";
        list($claim_baesong) = util::query_to_array($sql);
        if ($claim_baesong == null) return false;
        # 택배사 코드
        $inst = new courier();
        $dlvEtprsCd = $inst->get_courier_code($claim_baesong['baesong_company'], '11st');
        if ($dlvEtprsCd == '') $dlvEtprsCd = '00099';
        # 송장
        $invcNo = $claim_baesong['songjang'];

        $url = "http://api.11st.co.kr/rest/claimservice/exchangereqconf/{$clmReqSeq}/{$ordNo}/{$ordPrdSeq}/{$dlvEtprsCd}/{$invcNo}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '교환승인';
        $log['jp_number'] = $order['product_number'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = var_export($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = var_export($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }
    /**
     * 교환거부처리
     * public function exchangereqreject()
     * {
     * $order = $this->get_order_data();
     * $ordNo = $order['coop_ordNo'];
     * $ordPrdSeq = $order['coop_ordSeq'];
     * $clmReqSeq = $order['coop_clmNo'];
     * # 사유코드 : 현재는 '고객 교환신청 철회 대행으로 설정. 추가개발 필요
     * $refsRsnCd = '202';
     * # 사유
     * $refsRsn = '-';
     *
     * $url = "http://api.11st.co.kr/rest/claimservice/exchangereqreject/{$ordNo}/{$ordPrdSeq}/{$clmReqSeq}/{$refsRsnCd}/{$refsRsn}";
     * $method = 'GET';
     *
     * $response = parent::request($url,$method);
     * $response_array = parent::xml_to_array($response);
     *
     * # 로그준비
     * $log = array();
     * $log['title'] = '교환거부';
     * $log['jp_number'] = $order['product_number'];
     * $log['ord_no'] = $order['coop_ordNo'];
     * $log['ord_seq'] = $order['coop_ordSeq'];
     * $log['api_url'] = '11st';
     *
     * # RESPONSE IS NOT XML
     * if( $response_array == false )
     * {
     * $log['result_code'] = 'ERROR';
     * $log['result_msg'] = 'RESPONSE IS NOT XML';
     * $log['result_text'] = var_export($response,true);
     * $return = false;
     * }
     * # 성공
     * elseif( $response_array['result_code'] == '0' )
     * {
     * $log['result_code'] = 'SUCCESS';
     * $return = true;
     * }
     * # 실패
     * else
     * {
     * $log['result_code'] = 'Fault';
     * $log['result_msg'] = $response_array['result_text'];
     * $log['result_text'] = var_export($response_array,true);
     * $return = false;
     * }
     * util::insert_array('coop_api_log',$log);
     *
     * return $return;
     * }
     */
    /**
     * 반품승인처리
     */
    public function returnreqconf()
    {
        $order = $this->get_order_data();

        $clmReqSeq = $order['coop_clmNo'];
        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $url = "http://api.11st.co.kr/rest/claimservice/returnreqconf/{$clmReqSeq}/{$ordNo}/{$ordPrdSeq}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '반품승인';
        $log['jp_number'] = $order['product_number'];
        if ($_COOKIE['ad_id']) $log['reg_id'] = $_COOKIE['ad_id'];
        elseif ($GLOBALS['mem_id']) $log['reg_id'] = $GLOBALS['mem_id'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }
    /**
     * 반품거부처리 : 개발안함
     * public function 반품거부처리()
     * {
     * $url = "http://api.11st.co.kr/rest/claimservice/returnreqreject/{$ordNo}/{$ordPrdSeq}/{$clmReqSeq}/{$refsRsnCd}/{$refsRsn}";
     * $method = 'GET';
     * }
     */
    /**
     * 반품신청및완료
     * 구매자를 대신하여 반품신청을 합니다.
     * 반품완료로 바로 처리도 가능합니다.
     * public function 반품신청및완료()
     * {
     * $url = "http://api.11st.co.kr/rest/claimservice/sellerclaimfix/{$ordNo}/{$ordPrdSeq}/{$clmReqRsn}/{$claimProcess}/{$dlvEtprsCd}/{$invcNo}/{$dlvMthdCd}/{$clmReqCont}/{$clmDlvCstMthd}";
     * $method = 'GET';
     * }
     */
    // 반품보류
    public function returndefer()
    {
        $order = $this->get_order_data();
        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $clmReqSeq = $order['coop_clmNo'];
        $deferRefsRsnCd = '105';// 임의 대로 넣어야함
        $deferRefsRsn = '-'; //사유
        $url = "http://api.11st.co.kr/rest/claimservice/returnclaimdefer/{$ordNo}/{$ordPrdSeq}/{$clmReqSeq}/{$deferRefsRsnCd}/{$deferRefsRsn}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '반품보류';
        $log['jp_number'] = $order['product_number'];
        if ($_COOKIE['ad_id']) $log['reg_id'] = $_COOKIE['ad_id'];
        elseif ($GLOBALS['mem_id']) $log['reg_id'] = $GLOBALS['mem_id'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 반품보류 실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 반품보류 실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response_array, true);
            $return = false;
        }
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    // 반품완료보류 처리
    public function returnCpTdefer()
    {
        $order = $this->get_order_data();
        $ordNo = $order['coop_ordNo'];
        $ordPrdSeq = $order['coop_ordSeq'];
        $clmReqSeq = $order['coop_clmNo'];
        $deferRefsRsnCd = '105';// 임의 대로 넣어야함
        $deferRefsRsn = '-'; //사유
        $url = "http://api.11st.co.kr/rest/claimservice/returncompletedefer/{$ordNo}/{$ordPrdSeq}/{$clmReqSeq}/{$deferRefsRsnCd}/{$deferRefsRsn}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        # 로그준비
        $log = array();
        $log['title'] = '반품완료보류';
        $log['jp_number'] = $order['product_number'];
        if ($_COOKIE['ad_id']) $log['reg_id'] = $_COOKIE['ad_id'];
        elseif ($GLOBALS['mem_id']) $log['reg_id'] = $GLOBALS['mem_id'];
        $log['ord_no'] = $order['coop_ordNo'];
        $log['ord_seq'] = $order['coop_ordSeq'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            # develop_alert 테이블에 log
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 반품보류 실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);
            $return = false;
        } # 성공
        elseif ($response_array['result_code'] == '0') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            # develop_alert 테이블에 log
            $DA = array();
            $DA['reg_date'] = $log['reg_date'];
            $DA['description'] = '[11번가] 반품보류 실패';
            $DA['source'] = $url . PHP_EOL . $response;
            util::insert_array('developer_alert', $DA);

            $log['result_code'] = 'Fault';
            $log['result_msg'] = $response_array['result_text'];
            $log['result_text'] = print_r($response_array, true);
            $return = false;
        }
        # coop_api_log 테이블에 log
        util::insert_array('coop_api_log', $log);

        return $return;
    }

    public function get_11st_order_stats()
    {
        $order = $this->get_order_data();
        $ordNo = $order['coop_ordNo'];
        $url = "https://api.11st.co.kr/rest/claimservice/orderlistalladdr/{$ordNo}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        if ($response_array == false) {
            return false;
        } elseif (isset($response_array['order'][0])) {
            list($searched) = util::searchByKeyValue($response_array['order'], 'ordPrdSeq', $order['coop_ordSeq'], 1);
            return $searched;
        } else {
            return $response_array['order'];
        }
    }
}
