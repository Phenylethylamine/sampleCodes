<?php
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.php");
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.order.php");

/**
 * 주문 목록 처리
 * 신규주문/취소/교환/반품 목록 조회 및 DB 반영
 */
class st11_order_list extends st11
{
    /*
     * 신규 주문 조회
     * $startTime : YYYYMMDDhhmm (기본값:30분전)
     * $endTime : YYYYMMDDhhmm (기본값:현재)
     */
    public function get_new_order_list($startTime = null, $endTime = null)
    {
        if ($startTime == null) $startTime = date('YmdHi', strtotime('-30 minutes'));
        else $startTime = date('YmdHi', $startTime);
        if ($endTime == null) $endTime = date('YmdHi');
        else $endTime = date('YmdHi', $endTime);

        $url = "https://api.11st.co.kr/rest/ordservices/complete/{$startTime}/{$endTime}";
        $method = 'GET';
        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        $log = array();
        $log['title'] = '주문조회';
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        } # 성공
        elseif ($response_array['order']) {
            if (isset($response_array['order'][0])) {
                $response_array = $response_array['order'];
            } else {
                $response_array = array($response_array['order']);
            }
            $log['result_code'] = 'SUCCESS';
            $log['result_text'] = print_r($response, true);
            util::insert_array('coop_api_log', $log);

            echoLog('11st_order_new', $response);

            return $response_array;
        } # 신규 주문 없음
        elseif ($response_array['result_code'] == '0') {
            return array();
        } # 기타 오류
        else {
            $log['result_code'] = 'Fault';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        }
    }

    /**
     * 신규 주문 조회 입력
     * $order_data가 null이면 내부에서 $this->get_new_order_list()를 실행합니다
     */
    public function insert_new_order_list($order_data = null)
    {
        if ($order_data == null) {
            $order_data = $this->get_new_order_list();
        }

        if (is_array($order_data) == false) {
            return false;
        }

        $order_count = count($order_data);
        if ($order_count == 0) return true;

        # 먼저 들어온 주문부터 입력될 수 있도록 배열 역순으로 정렬
        $order_data = array_reverse($order_data);

        # 11번가 상품번호 추출
        $order_product_number_list = array();
        for ($i = 0; $i < $order_count; ++$i) {
            $order = &$order_data[$i];
            $order_product_number_list[] = "'{$order['sellerPrdCd']}'";
        }
        $order_product_number_list = implode(',', $order_product_number_list);

        # 주문 상품 데이터 준비
        $sql_product = "
			SELECT		AP.*,
						HM.user_hphone,
						HM.user_phone2,
						( SELECT option_value FROM happy_member_option WHERE user_id = AP.id AND option_field = 'susuryo' ) AS susuryo
			FROM		auction_product AS AP
			LEFT OUTER JOIN (회원테이블) AS HM ON AP.id = HM.user_id
			WHERE		AP.number IN ({$order_product_number_list})
		";
        $product_data = util::query_to_array($sql_product);

        # 제휴할인가 데이터 준비
        if (count($product_data)) {
            $product_numbers = util::array_column($product_data, 'number');
            $product_numbers = implode(',', $product_numbers);
            $sql_price = "SELECT * FROM auction_product_price WHERE product_number IN ({$product_numbers}) AND target = '골프딜제휴할인'";
            $price_data = util::query_to_array($sql_price);
        } else {
            $price_data = array();
        }

        # 공통데이터
        $time = time();
        $reg_date = date('Y-m-d H:i:s');
        for ($i = 0; $i < $order_count; ++$i) {
            $order = &$order_data[$i];

            # 직매입 주문건 분기처리
            if ($order['prdNo'] == '2058177270') {
                if ($GLOBALS['print_process']) {
                    echoDev('직매입 주문건 분기처리 시작');
                }
                self::insert_new_order_directBuy($order);
                continue;
            }

            # 이미 INSERT 되어있는 경우 발주확인만 하고 종료
            $sql_check = "
                SELECT		COUNT(*)
                FROM		(주문테이블) AS AJ
                WHERE		AJ.coop_ordNo = '{$order['ordNo']}'
                AND			AJ.coop_ordSeq = '{$order['ordPrdSeq']}'
                AND			AJ.site = '11st'
            ";
            list($check) = mysql_fetch_row(query($sql_check));
            if ($check) {
                $DA = array();
                $DA['reg_date'] = $this->get_reg_date();
                $DA['description'] = '[11번가/신규주문입력] 이미 존재하는 주문. 수동발주확인필요.';
                $DA['source'] = print_r($order, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }

            # 상품 데이터 확인
            $match_product = null;
            list($match_product) = util::searchByKeyValue($product_data, 'number', $order['sellerPrdCd'], 1);
            # 상품이 없을경우
            if ($match_product == null) {
                $DA = array();
                $DA['reg_date'] = $reg_date;
                $DA['description'] = '[11번가] 신규 주문이 확인되었으나 상품을 확인할 수 없습니다';
                $DA['source'] = print_r($order, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }

            # 가격 데이터 확인
            $match_price = null;
            for ($j = 0, $endj = count($price_data); $j < $endj; ++$j) {
                $price_row = $price_data[$j];
                if ($match_product['number'] == $price_row['product_number'] &&
                    $price_row['adjusted_price'] == $order['selPrc']) {
                    $match_price = $price_row;
                    break;
                }
            }


            # 난수 생성 0~100
            $rand = rand(0, 100);
            # 주문번호 생성 : $time + 0~100
            $order_number = "{$time}-{$rand}";
            # 비회원ID 생성
            $order_id = "비회원_{$time}_{$rand}";
            # 다음 주문번호 생성을 위해 time값 1 증가
            ++$time;


            # (1) (주문테이블) 입력 시작
            $JP = array();
            $JP['gou_number'] = $order_number;
            $JP['product_number'] = $order['sellerPrdCd'];
            $JP['product_stats'] = 3;
            $JP['category'] = $match_product['category'];
            $JP['buyer_id'] = $order_id;
            $JP['seller_id'] = $match_product['id'];
            $JP['title'] = $match_product['product_name'];
            # 추가옵션금액
            #$JP['etc_price']
            # 판매단가
            $JP['price'] = $order['selPrc'];
            # 제휴할인가
            if ($match_price !== null)
                $JP['discount_price'] = ($match_price['standard_price'] - $match_price['adjusted_price']) * $order['ordQty'];
            $JP['org_price'] = $match_product['baro_price'];
            $JP['quantity'] = $order['ordQty'];
            # 배송료 구분
            switch ($order['dlvCstType']) {
                # 선불(결제완료)
                case '01' :
                    $JP['baesong_type'] = 2;
                    $JP['baesongbi'] = $order['dlvCst'];
                    break;
                # 착불
                case '02' :
                    $JP['baesong_type'] = 1;
                    $JP['baesongbi'] = 0;
                    break;
                # 무료
                case '03' :
                    $JP['baesong_type'] = 0;
                    $JP['baesongbi'] = 0;
                    break;
            }
            # 도서산간배송비가 선결제 된 경우
            if ($order['bmDlvCstType'] == '01') $JP['baesongbi'] += $order['bmDlvCst'];
            $JP['baesong_cut_free'] = $match_product['baesong_cut_free'];
            $JP['baesong_tie'] = $match_product['baesong_tie'];
            $JP['reg_date'] = $reg_date;
            # 옵션추가금액
            $JP['add_price1'] = $order['ordOptWonStl'];
            # 주문총액
            $JP['refund_money'] = $order['ordAmt'];
            $JP['ipgum_date'] = $reg_date;
            $JP['product_baesongbi'] = $match_product['baesongbi'];
            $JP['site'] = '11st';
            # 제휴 주문번호
            $JP['coop_ordNo'] = $order['ordNo'];
            # 제휴 주문순번
            $JP['coop_ordSeq'] = $order['ordPrdSeq'];
            # 제휴 배송번호
            $JP['coop_dlvNo'] = $order['dlvNo'];
            # 입력
            util::insert_array('auction_jangproduct', $JP);

            # JP_NUMBER 추출
            $jangproduct_number = mysql_insert_id();

            # jangproduct_log 입력
            $JPL = array();
            $JPL['order_no'] = $jangproduct_number;
            $JPL['user_id'] = '11st';
            $JPL['user_ip'] = '11st';
            $JPL['gubun'] = 3;
            $JPL['reg_date'] = $reg_date;
            util::insert_array('auction_jangproduct_log', $JPL);


            # (2) auction_jangproduct_ext 입력
            # 옵션없음
            if ($order['slctPrdOptNm'] == '') {
                $JPE = array();
                $JPE['tmp_cart_id'] = '11st';
                $JPE['cart_number'] = 0;
                $JPE['jp_number'] = $jangproduct_number;
                $JPE['product_number'] = $match_product['number'];
                $JPE['add_type'] = 'product';
                $JPE['title'] = '없음 없음';
                $JPE['price'] = 0;
                $JPE['quantity'] = $order['ordQty'];
                $JPE['buy_type'] = 1;
                $JPE['reg_date'] = $reg_date;
            } # 옵션상품
            else {
                $JPE = array();
                $JPE['tmp_cart_id'] = '11st';
                $JPE['cart_number'] = 0;
                $JPE['jp_number'] = $jangproduct_number;
                $JPE['product_number'] = $match_product['number'];
                $JPE['cate1_number'] = $order['sellerStockCd'] ? $order['sellerStockCd'] : 0;
                $JPE['cate2_number'] = $order['sellerStockCd'] ? $order['sellerStockCd'] : 0;
                $JPE['add_type'] = 'product';
                # 옵션정보 파싱
                $option_name = $order['slctPrdOptNm'];
                # 접두어절 '상품옵션:' 을 자릅니다
                $option_name = substr($option_name, strpos($option_name, ':') + 1);
                # 접미어절 (+100원) 을 자릅니다
                if ($order['ordOptWonStl'] > 0) {
                    $option_name = substr($option_name, 0, strrpos($option_name, ' '));
                }
                $JPE['title'] = addslashes($option_name);
                # 옵션단가 = 옵션결제금액 / 주문수량
                $JPE['price'] = $order['ordOptWonStl'] / $order['ordQty'];
                $JPE['quantity'] = $order['ordQty'];
                $JPE['buy_type'] = 1;
                $JPE['reg_date'] = $reg_date;
            }
            util::insert_array('auction_jangproduct_ext', $JPE);


            # (3) auction_commission 입력
            $AC = array();
            $AC['jangproduct_number'] = $jangproduct_number;
            $AC['product_number'] = $match_product['number'];
            $AC['or_no'] = $order_number;
            $AC['seller_id'] = $match_product['id'];
            $AC['buyer_id'] = $order_id;
            $AC['soosooryo'] = $match_product['susuryo'];
            $AC['soosooryo_type'] = 'member';
            $AC['soosooryo_money'] = buyer_susuryo_new($order['ordAmt'] + $JP['discount_price'], 1, $match_product['id'], 1);
            $AC['reg_date'] = $reg_date;

            # 굿샷딜-공급가액고정 상품일 경우
            $GSDL = GoodShotDealList::getInstance();
            $gsd_row = $GSDL->getOngoingList($match_product['number']);
            if (is_numeric($gsd_row['supply_price'])) {
                $정산예정액 = ($gsd_row['supply_price'] * $JP['quantity']) + $JP['add_price1'];
                $상품금액합계 = $order['ordAmt'];
                $AC['soosooryo_money'] = $상품금액합계 - $정산예정액;
                $AC['soosooryo'] = 0;
            }
            util::insert_array('auction_commission', $AC);

            # (4) auction_jangboo
            $JB = array();
            $JB['or_no'] = $order_number;
            $JB['reg_date'] = $reg_date;
            $JB['name'] = $order['ordNm'];
            $JB['id'] = $order_id;
            $JB['phone'] = $order['ordTlphnNo'];
            $JB['product_title'] = $match_product['product_name'];
            $JB['hphone'] = $order['ordPrtblTel'];
            $JB['r_name'] = $order['rcvrNm'];
            $JB['r_phone'] = $order['rcvrPrtblNo'];
            $JB['zip'] = $order['rcvrMailNo'];
            $JB['addr1'] = $order['rcvrBaseAddr'];
            $JB['addr2'] = $order['rcvrDtlsAddr'];
            $JB['comment'] = $order['ordDlvReqCont'];
            $JB['total_price'] = $order['ordAmt'];
            $JB['in_type'] = '11번가';
            $JB['pg_susuryotype'] = $match_product['susuryotype'];
            util::insert_array('auction_jangboo', $JB);

            if (!$GLOBALS['do_not_send_sms']) {
                # 판매자에게 푸쉬
                if ($GLOBALS['version']['use_fcm']) {
                    $firebaseCloudMessageBuilder = new FirebaseCloudMessageBuilder('MypageActivity', array('mode' => 'S', 'stats' => '3', 'text' => $JP['title']));
                    $message = $firebaseCloudMessageBuilder->addTargetById($JP['seller_id'])->build();
                    FirebaseCloudMessage::send($message);
                } else {
                    sendNotification_Order($order_number, "order_sell", "3", "상품 판매 알림");
                }

                # 판매자 연락처 목록
                if ($match_product['id'] == 'pricegolf') {
                    global $shop_seller;
                    $sms_getter_list = $shop_seller;
                } else {
                    # user_hphone, user_phone2로 문자 발송
                    $sms_getter_list = array();
                    if ($match_product['user_hphone']) $sms_getter_list[] = $match_product['user_hphone'];
                    if ($match_product['user_phone2']) $sms_getter_list[] = $match_product['user_phone2'];
                }
                # 판매자에게 문자 발송
                foreach ($sms_getter_list AS $sms_getter) {
                    global $site_phone, $cfg, $sms_testing;
                    send_sms_msg('pricegolf', $sms_getter, $site_phone, $cfg['sms']['seller'][3], 'order', $sms_testing, $order_number);
                }
            }


            # 옵션재고차감
            if ($order['sellerStockCd'] != '') {
                $sql_select_option = "
					SELECT		*
					FROM		(옵션정보테이블)
					WHERE		product_number = '{$match_product['number']}'
				";
                $option_data = util::query_to_array($sql_select_option);

                # 옵션재고합계
                $sum_option_jaego = 0;

                # 옵션 일치 합계
                $sum_accord = 0;

                # 주문들어온옵션
                $ordered_option_row = null;
                for ($j = 0, $end = count($option_data); $j < $end; ++$j) {
                    $option_row = $option_data[$j];

                    $sum_option_jaego += $option_row['jaego'];

                    if ($option_row['number'] == $order['sellerStockCd']) {
                        $ordered_option_row = $option_row;
                        $sum_accord += 1;
                    }
                }


                # 옵션재고차감
                $update_option_jaego = $ordered_option_row['jaego'] - $order['ordQty'];
                if ($update_option_jaego < 0) $update_option_jaego = 0;

                # 옵션이 일치하지 않다면,, 옵션 업데이트를 시키지 않는다.180627 이용범
                if ($sum_accord == 0) {
                    echoLog('11st_order_option_fail', $order);
                    $DA = array();
                    $DA['reg_date'] = $GLOBALS['YMDHIS'];
                    $DA['description'] = '[11번가] 판매자 옵션번호가 다른 문제로 재고차감X';
                    $DA['source'] = print_r($order, true);
                    util::insert_array('developer_alert', $DA);
                } else {
                    $sql_update_poi = "
						UPDATE (옵션정보테이블) SET
						jaego = {$update_option_jaego}
						WHERE number = {$order['sellerStockCd']}
					";
                    query($sql_update_poi);
                }

                # 통합재고차감
                $update_sum_option_jaego = $sum_option_jaego - $order['ordQty'];
                $sql_update_ap = "
					UPDATE (상품테이블) SET
						jaego = {$update_sum_option_jaego}
					WHERE number = {$match_product['number']}
				";
                query($sql_update_ap);

                # $need_product_stop
                $need_product_stop = ($update_sum_option_jaego < 1);
                # $need_vacct_close
                $need_vacct_close = ($update_option_jaego < 1);
            } # 일반재고차감
            else {
                $update_jaego = $match_product['jaego'] - $order['ordQty'];
                $sql_update_ap = "
					UPDATE (상품테이블) SET
						jaego = {$update_jaego}
					WHERE number = {$match_product['number']}
				";
                query($sql_update_ap);

                # $need_product_stop
                $need_product_stop = $update_jaego < 1;
                # $need_vacct_close
                $need_vacct_close = $update_jaego < 1;
            }


            # 판매종료
            if ($need_product_stop) {
                $sql_stop = "
					UPDATE (상품테이블) SET
						product_stats = 1
					WHERE number = {$match_product['number']}
				";
                query($sql_stop);

                # 재고소진 종료 로그
                $GDE = array();
                $GDE['regdt'] = $reg_date;
                $GDE['mem_id'] = $match_product['id'];
                $GDE['goods_code'] = $match_product['number'];
                $GDE['type'] = '1';
                $GDE['end_type'] = 'S100';
                $GDE['memo'] = '재고소진으로 인한 자동판매종료';
                util::insert_array('goods_direct_endcontinue', $GDE);
            }


            # 가상계좌 닫기
            if ($need_vacct_close) {
                $sql_vact = "
					SELECT		A.kcp_tno
					FROM		(주문테이블) AS A
					WHERE		A.product_number = '{$match_product['number']}'
					AND			A.gou_number <> '{$order_number}'
					AND			A.kcp_tno <> ''
					AND			A.product_stats = 2
					AND			A.reg_date > DATE_ADD(NOW(), INTERVAL -1 DAY)
				";
                $result_vact = query($sql_vact);
                while ($vact = mysql_fetch_assoc($result_vact)) {
                    cancel_VACT($vact['kcp_tno']);
                }
            }


            # 제휴사 상품 수정 : 11번가 예외
            modifyCoopProduct($match_product['number']);


            # 발주확인처리
            $inst = new st11_order($jangproduct_number);
            $inst->reqpackaging();

            # 루프 종료
        }
        return true;
    }

    /**
     * 신규주문 중 직매입 의뢰건 별도 처리
     */
    static function insert_new_order_directBuy($order)
    {
        $sql = "
			SELECT	COUNT(*)
			FROM	direct_buy AS A
			WHERE	A.order_no = '{$order['ordNo']}'
		";
        list($exists) = mysql_fetch_row(query($sql));
        if ($exists) {
            $DA = array();
            $DA['reg_date'] = $GLOBALS['YMDHIS'];
            $DA['description'] = '[11번가] 직매입 발주확인 필요';
            $DA['source'] = print_r($order, true);
            util::insert_array('developer_alert', $DA);
            if ($GLOBALS['print_process']) echoDev($DA);
            return true;
        }

        # 입력처리
        $param = array();
        $param['store'] = '11번가';
        $param['order_no'] = $order['ordNo'];
        $param['user_id'] = $order['memID'];

        if ($order['ordNm'] === $order['rcvrNm']) $param['name'] = $order['rcvrNm'];
        else $param['name'] = "{$order['rcvrNm']}({$order['ordNm']})";

        $order['rcvrPrtblNo'] = str_replace('-', '', $order['rcvrPrtblNo']);
        $param['phone'] = $order['rcvrPrtblNo'];
        $param['zip_code'] = $order['rcvrMailNo'];
        $param['base_addr'] = $order['rcvrBaseAddr'];
        $param['detail_addr'] = $order['rcvrDtlsAddr'];
        $param['new_message'] = 1;
        $param['reg_date'] = $GLOBALS['YMDHIS'];
        $param_extra = array();
        $param_extra['ordPrdSeq'] = $order['ordPrdSeq'];
        $param_extra['dlvNo'] = $order['dlvNo'];
        $param_extra['rcvrTlphn'] = str_replace('-', '', $order['rcvrTlphn']);
        $param_extra['ordPrdtbTel'] = str_replace('-', '', $order['ordPrtblTel']);
        $param_extra['ordTlphnNo'] = str_replace('-', '', $order['ordTlphnNo']);
        $param['extra'] = util::array_to_json($param_extra);
        DirectBuy::create($param);

        # 발주처리
        $order['product_number'] = 'directBuy';

        # 알림톡
        $send = array();
        $send['template_title'] = '직매입 시작';
        $send['tel_num'] = $order['rcvrPrtblNo'];
        $send['btn_url'] = 'http://pricegolf.co.kr/directBuy.php?orderNo=' . $order['ordNo'];
        $send['use_sms'] = 1;
        $send['args'] = array();
        $send['args']['NAME'] = $param['name'];
        KakaoNoticeTalk::sendFromDirect($send);

        return st11_order::reqpackagingManual($order);
    }

    /**
     * 취소요청 목록 조회
     * $startTime : YYYYMMDDhhmm (기본값:30분전)
     * $endTime : YYYYMMDDhhmm (기본값:현재)
     */
    public function get_cancel_order_list($startTime = null, $endTime = null)
    {
        if ($startTime == null) $startTime = date('YmdHi', strtotime('-30 minutes'));
        else $startTime = date('YmdHi', $startTime);
        if ($endTime == null) $endTime = date('YmdHi');
        else $endTime = date('YmdHi', $endTime);
        $url = "http://api.11st.co.kr/rest/claimservice/cancelorders/{$startTime}/{$endTime}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        $log = array();
        $log['title'] = '주문취소조회';
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        } # 성공
        elseif ($response_array['order']) {
            if (isset($response_array['order'][0])) {
                $response_array = $response_array['order'];
            } else {
                $response_array = array($response_array['order']);
            }
            $log['result_code'] = 'SUCCESS';
            $log['result_text'] = print_r($response, true);
            util::insert_array('coop_api_log', $log);

            echoLog('11st_order_cancel', $response);

            return $response_array;
        } # 신규 주문 없음
        elseif ($response_array['result_code'] == '-1') {
            return array();
        } # 기타 오류
        else {
            $log['result_code'] = 'Fault';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        }
    }

    /**
     * 취소요청목록 조회결과 반영
     * $cancel_list가 null일 경우 $this->get_cancel_order_list()로 데이터를 가져옵니다
     */
    public function update_cancel_order_list($cancel_list = null)
    {
        if ($cancel_list == null) $cancel_list = $this->get_cancel_order_list();
        if ($cancel_list == false) return false;

        $reg_date = date('Y-m-d H:i:s');
        for ($i = 0, $end = count($cancel_list); $i < $end; ++$i) {
            $cancel = &$cancel_list[$i];

            # 직매입 취소
            if ($cancel['prdNo'] === '2058177270') {
                DirectBuy::cancel($cancel);
                continue;
            }

            # 주문 데이터 확인
            $sql = "
				SELECT		AJ.*,
							APC.coop_number,
							HM.user_hphone AS seller_hphone
				FROM		(주문테이블) AS AJ
				LEFT OUTER JOIN (제휴상품연동정보테이블) AS APC ON AJ.product_number = APC.product_number
				LEFT OUTER JOIN (회원테이블) AS HM ON AJ.seller_id = HM.user_id
				WHERE		AJ.coop_ordNo = '{$cancel['ordNo']}'
				AND			AJ.coop_ordSeq = '{$cancel['ordPrdSeq']}'
				AND			APC.coop_number = '{$cancel['prdNo']}'
			";
            $order = null;
            list($order) = util::query_to_array($sql);
            # 자사에 데이터가 없을 경우
            if ($order == null) {
                $DA = array();
                $DA['reg_date'] = $reg_date;
                $DA['description'] = '[11번가] 취소요청이 들어왔으나 확인되지 않은 주문입니다';
                $DA['source'] = print_r($cancel, true) . PHP_EOL . '취소전체' . print_r($cancel_list, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }
            # 이미 반영되어 있으면
            if ($order['product_stats'] == '7') {
                continue;
            }
            # 구매취소철회
            if ($order['number'] == '313957') continue;
            # 입금완료,판매취소 상태가 아니면
            if (in_array($order['product_stats'], array('3', '11')) == false) {
                $DA = array();
                $DA['reg_date'] = $reg_date;
                $DA['description'] = '[11번가]취소요청이 들어왔으나 입금완료 상태가 아닙니다';
                $DA['source'] = print_r($cancel, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }
            # 전체 취소가 아닐 경우(취소요청 수량이 주문 수량보다 적을경우)
            if ($cancel['ordCnQty'] < $order['quantity']) {
                # 클레임 대응 발송처리가 가능하도록 클레임번호 저장
                $sql_update = "
					UPDATE (주문테이블) SET
						coop_clmNo = '{$cancel['ordPrdCnSeq']}'
					WHERE number = {$order['number']}
				";
                query($sql_update);

                $DA = array();
                $DA['reg_date'] = $reg_date;
                $DA['description'] = '부분 취소 요청';
                $DA['source'] = print_r($cancel, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }


            # auction_jangproduct
            $memo_cancel = array('구매자취소');
            // 클레임 코드를 문자열로 저장합니다.
            /*
             * 클레임 사유코드
                → 00 : 등록주체 구매자 : 무통장 미입금 취소
                → 04 : 등록주체 구매자 : 판매자의 배송 처리가 늦음
                → 06 : 등록주체 구매자 : 판매자의 상품 정보가 잘못됨 등록주체 판매자 : 배송 지연 예상
                → 07 : 등록주체 구매자 : 동일 상품 재주문(주문정보수정) 등록주체 판매자 : 상품/가격 정보 잘못 입력
                → 08 : 등록주체 구매자 : 주문상품의 품절/재고없음 등록주체 판매자 : 상품 품절(전체옵션)
                → 09 : 등록주체 구매자 : 11번가 내 다른 상품으로 재주문 등록주체 판매자 : 옵션 품절(해당옵션)
                → 10 : 등록주체 구매자 : 타사이트 상품 주문 등록주체 판매자 : 고객변심
                → 11 : 등록주체 구매자 : 상품에 이상없으나 구매 의사 없어짐
                → 12 : 등록주체 구매자 : 기타(구매자 책임사유)
                → 13 : 등록주체 구매자 : 기타(판매자 책임사유)
                → 99 : 등록주체 구매자 : 기타
             */
            $memo_cancel_code = $cancel['ordCnRsnCd'];
            switch ($memo_cancel_code) {
                case "00" :
                    $memo_cancel[] = "무통장 미입금 취소";
                    break;
                case "04" :
                    $memo_cancel[] = "판매자의 배송 처리가 늦음";
                    break;
                case "06" :
                    $memo_cancel[] = "판매자의 상품 정보가 잘못됨";
                    break;
                case "07" :
                    $memo_cancel[] = "동일 상품 재주문(주문정보수정)";
                    break;
                case "08" :
                    $memo_cancel[] = "주문상품의 품절/재고없음";
                    break;
                case "09" :
                    $memo_cancel[] = "다른 상품으로 재주문";
                    break;
                case "10" :
                    $memo_cancel[] = "타사이트 상품 주문";
                    break;
                case "11" :
                    $memo_cancel[] = "상품에 이상없으나 구매 의사 없어짐";
                    break;
                case "12" :
                case "99" :
                    $memo_cancel[] = "기타(구매자 책임사유)";
                    break;
                case "13" :
                    $memo_cancel[] = "기타(판매자 책임사유)";
                    break;
                case "14" :
                    $memo_cancel[] = "구매의사 없어짐";
                    break;
                case "15" :
                    $memo_cancel[] = "색상/사이즈/주문정보 변경";
                    break;
                case "16" :
                    $memo_cancel[] = "다른 상품 잘못 주문";
                    break;
                case "17" :
                    $memo_cancel[] = "배송지연으로 취소";
                    break;
                case "18" :
                    $memo_cancel[] = "상품품절, 재고없음";
                    break;
                default :
                    $memo_cancel[] = "기타";
                    break;
            }

            if ($cancel['ordCnDtlsRsn'] != "") {
                $memo_cancel[] = addslashes($cancel['ordCnDtlsRsn']);
            }
            $memo_cancel = implode('<divider>', $memo_cancel);


            $sql_update = "
				UPDATE (주문테이블) SET
					product_stats = '7',
					memo_cancel = '{$memo_cancel}',
					coop_clmAct = 'Y',
					coop_clmNo = '{$cancel['ordPrdCnSeq']}'
				WHERE number = {$order['number']}
			";
            query($sql_update);

            # auction_jangproduct_log
            $AJL = array();
            $AJL['order_no'] = $order['number'];
            $AJL['user_id'] = '11st';
            $AJL['user_ip'] = '11st';
            $AJL['gubun'] = '7';
            $AJL['reg_date'] = $reg_date;
            util::insert_array('auction_jangproduct_log', $AJL);

            # 판매자에게 푸쉬
            if ($GLOBALS['version']['use_fcm']) {
                $firebaseCloudMessageBuilder = new FirebaseCloudMessageBuilder('MypageActivity', array('mode' => 'S', 'stats' => '7'));
                $message = $firebaseCloudMessageBuilder->addTargetById($order['seller_id'])->build();
                FirebaseCloudMessage::send($message);
            } else {
                sendNotification_Order($order['gou_number'], "order_sell", "7", "구매자가 구매를 취소하였습니다.", $order['number']);
            }
            # 판매자에게 SMS 전송
            send_sms_msg("pricegolf", $order['seller_hphone'], $GLOBALS['site_phone'], $GLOBALS['cfg']['sms']['seller'][7], "order", $GLOBALS['sms_testing'], $order['number']);
        }
    }

    /**
     * 교환요청 목록 조회
     * $startTime : YYYYMMDDhhmm (기본값:30분전)
     * $endTime : YYYYMMDDhhmm (기본값:현재)
     */
    public function get_exchange_order_list($startTime = null, $endTime = null)
    {
        if ($startTime == null) $startTime = date('YmdHi', strtotime('-30 minutes'));
        else $startTime = date('YmdHi', $startTime);
        if ($endTime == null) $endTime = date('YmdHi');
        else $endTime = date('YmdHi', $endTime);
        $url = "http://api.11st.co.kr/rest/claimservice/exchangeorders/{$startTime}/{$endTime}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        $log = array();
        $log['title'] = '교환요청조회';
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        } # 성공
        elseif ($response_array['order']) {
            if (isset($response_array['order'][0])) {
                $response_array = $response_array['order'];
            } else {
                $response_array = array($response_array['order']);
            }
            $log['result_code'] = 'SUCCESS';
            $log['result_text'] = print_r($response, true);
            util::insert_array('coop_api_log', $log);

            echoLog('11st_order_exchange', $response);

            return $response_array;
        } # 신규 주문 없음
        elseif ($response_array['result_code'] == '-1') {
            return array();
        } # 기타 오류
        else {
            $log['result_code'] = 'Fault';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        }
    }

    /**
     * 교환요청목록 조회결과 반영
     * $exchange_list null일 경우 $this->get_exchange_order_list()로 데이터를 가져옵니다
     */
    public function update_exchange_order_list($exchange_list = null)
    {
        if ($exchange_list == null) $exchange_list = $this->get_exchange_order_list();

        if (is_array($exchange_list) == false) return false;

        $reg_date = date('Y-m-d H:i:s');
        for ($i = 0, $end = count($exchange_list); $i < $end; ++$i) {
            $row = &$exchange_list[$i];

            if ($row['ordNo'] == '') {
                $DA = array();
                $DA['description'] = "[11번가] 교환요청 반영 데이터 오류";
                $DA['source'] = print_r($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }

            # 주문 데이터 확인
            $sql = "
				SELECT		AJ.*,
							APC.coop_number,
							HM.user_hphone AS seller_hphone
				FROM		(주문테이블) AS AJ
				LEFT OUTER JOIN (제휴상품연동정보테이블) AS APC ON AJ.product_number = APC.product_number
				LEFT OUTER JOIN (회원테이블) AS HM ON AJ.seller_id = HM.user_id
				WHERE		AJ.coop_ordNo = '{$row['ordNo']}'
				AND			AJ.coop_ordSeq = '{$row['ordPrdSeq']}'
				AND			APC.coop_number = '{$row['prdNo']}'
			";
            $order = null;
            list($order) = util::query_to_array($sql);
            # 자사에 데이터가 없을 경우
            if ($order == null) {
                $DA = array();
                $DA['description'] = "[11번가] 교환요청이 확인되었으나 확인할 수 없는 주문입니다";
                $DA['source'] = var_export($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }
            # 이미 반영된 요청건이면
            if ($order['product_stats'] == '17') {
                continue;
            }
            # 배송중 상태가 아니면
            if ($order['product_stats'] != '4') {
                $DA = array();
                $DA['description'] = '[11번가] 교환요청이 확인되었으나 배송중 상태가 아닙니다';
                $DA['source'] = var_export($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }

            # auction_jangproduct
            $memo_return = addslashes($row['clmReqCont']);
            $sql_update = "
				UPDATE (주문테이블) SET
					product_stats = '17',
					memo_return = '{$memo_return}',
					coop_clmAct = 'Y',
					coop_clmNo = '{$row['ordPrdCnSeq']}'
				WHERE number = {$order['number']}
			";
            query($sql_update);

            # auction_jangproduct_log
            $AJL = array();
            $AJL['order_no'] = $order['number'];
            $AJL['user_id'] = '11st';
            $AJL['user_ip'] = '11st';
            $AJL['gubun'] = '17';
            $AJL['reg_date'] = $reg_date;
            util::insert_array('auction_jangproduct_log', $AJL);

            # auction_jangproduct_claim_baesong
            $AJCB = array();
            $AJCB['jp_number'] = $order['number'];
            $AJCB['mode'] = '1';
            # 배송회사 확인
            $inst = new courier;
            $courier_data = $inst->get_courier_data();
            list($match_row) = util::searchByKeyValue($courier_data, '11st_code', $order['dlvEtprsCd'], 1);
            $courier_name = $match_row['name'] ? $match_row['name'] : "기타({$order['dlvEtprsCd']})";
            $AJCB['songjang'] = $order['twPrdInvcNo'];
            $AJCB['baesong_company'] = $courier_name;
            $AJCB['arrive_date'] = '';
            $AJCB['stats_comment'] = addslashes($row['clmReqCont']);
            $AJCB['regdate'] = $reg_date;
            util::insert_array('auction_jangproduct_claim_baesong', $AJCB);

            # 판매자에게 SMS 전송
            send_sms_msg("pricegolf", $order['seller_hphone'], $GLOBALS['site_phone'], $GLOBALS['cfg']['sms']['seller'][17], "order", "", $order['number']);
            # 판매자에게 푸쉬
            if ($GLOBALS['version']['use_fcm']) {
                $firebaseCloudMessageBuilder = new FirebaseCloudMessageBuilder('MypageActivity', array('mode' => 'S', 'stats' => '17', 'title' => $order['title']));
                $message = $firebaseCloudMessageBuilder->addTargetById($order['seller_id'])->build();
                FirebaseCloudMessage::send($message);
            } else {
                GCM_sendEventNotificationToUser($order['seller_id'], "order_sell", "17", "", "", $order['title'], "구매자가 교환을 요청하였습니다. 터치해주세요.");
            }
        }
    }

    /**
     * 반품요청 목록 조회
     * $startTime : YYYYMMDDhhmm (기본값:30분전)
     * $endTime : YYYYMMDDhhmm (기본값:현재)
     */
    public function get_return_order_list($startTime = null, $endTime = null)
    {
        if ($startTime == null) $startTime = date('YmdHi', strtotime('-30 minutes'));
        else $startTime = date('YmdHi', $startTime);
        if ($endTime == null) $endTime = date('YmdHi');
        else $endTime = date('YmdHi', $endTime);
        $url = "http://api.11st.co.kr/rest/claimservice/returnorders/{$startTime}/{$endTime}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        $log = array();
        $log['title'] = '반품요청조회';
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        } # 성공
        elseif ($response_array['order']) {
            if (isset($response_array['order'][0])) {
                $response_array = $response_array['order'];
            } else {
                $response_array = array($response_array['order']);
            }
            $log['result_code'] = 'SUCCESS';
            $log['result_text'] = print_r($response, true);
            util::insert_array('coop_api_log', $log);

            echoLog('11st_order_return', $response);

            return $response_array;
        } # 내역 없음
        elseif ($response_array['result_code'] == '-1') {
            return array();
        } # 기타 오류
        else {
            $log['result_code'] = 'Fault';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        }
    }

    /**
     * 반품요청목록 조회결과 반영
     * $return_list null일 경우 $this->get_return_order_list()로 데이터를 가져옵니다
     */
    public function update_return_order_list($return_list = null)
    {
        if ($return_list == null) $return_list = $this->get_return_order_list();
        if ($return_list == false) return false;

        $reg_date = date('Y-m-d H:i:s');
        for ($i = 0, $end = count($return_list); $i < $end; ++$i) {
            $row = &$return_list[$i];

            if ($row['ordNo'] == '') {
                $DA = array();
                $DA['reg_date'] = date('Y-m-d H:i:s');
                $DA['description'] = "[11번가] 반품요청 확인 데이터 오류";
                $DA['source'] = print_r($row, true) . PHP_EOL . '반품전체' . print_r($return_list, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }

            # 주문 데이터 확인
            $sql = "
				SELECT		AJ.*,
							APC.coop_number,
							HM.user_hphone AS seller_hphone
				FROM		(주문테이블) AS AJ
				LEFT OUTER JOIN (제휴상품연동정보테이블) AS APC ON AJ.product_number = APC.product_number
				LEFT OUTER JOIN (회원테이블) AS HM ON AJ.seller_id = HM.user_id
				WHERE		AJ.coop_ordNo = '{$row['ordNo']}'
				AND			AJ.coop_ordSeq = '{$row['ordPrdSeq']}'
				AND			APC.coop_number = '{$row['prdNo']}'
			";
            $order = null;
            list($order) = util::query_to_array($sql);
            # 자사에 데이터가 없을 경우
            if ($order == null) {
                $DA = array();
                $DA['description'] = "[11번가] 반품요청이 확인되었으나 확인할 수 없는 주문입니다";
                $DA['source'] = print_r($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }
            # 이미 반영된 요청건이면
            if ($order['product_stats'] == '8' || $order['product_stats'] == '9') {
                continue;
            }
            # 배송중 상태가 아니면
            if ($order['product_stats'] != '4') {
                $DA = array();
                $DA['description'] = '[11번가] 반품요청이 확인되었으나 배송중 상태가 아닙니다';
                $DA['source'] = print_r($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }

            # auction_jangproduct
            $memo_return = addslashes($row['clmReqCont']);
            $sql_update = "
				UPDATE (주문테이블) SET
					product_stats = '8',
					memo_return = '{$memo_return}',
					coop_clmAct = 'Y',
					coop_clmNo = '{$row['clmReqSeq']}'
				WHERE number = {$order['number']}
			";
            query($sql_update);

            # auction_jangproduct_log
            $AJL = array();
            $AJL['order_no'] = $order['number'];
            $AJL['user_id'] = '11st';
            $AJL['user_ip'] = '11st';
            $AJL['gubun'] = '8';
            $AJL['reg_date'] = $reg_date;
            util::insert_array('auction_jangproduct_log', $AJL);

            # auction_jangproduct_claim_baesong
            $AJCB = array();
            $AJCB['jp_number'] = $order['number'];
            $AJCB['mode'] = '0';
            # 배송회사 확인
            $inst = new courier;
            $courier_data = $inst->get_courier_data();
            list($match_row) = util::searchByKeyValue($courier_data, '11st_code', $row['dlvEtprsCd'], 1);
            $courier_name = $match_row['name'] ? $match_row['name'] : "기타({$row['dlvEtprsCd']})";
            $AJCB['songjang'] = $row['twPrdInvcNo'];
            $AJCB['baesong_company'] = $courier_name;
            $AJCB['arrive_date'] = '';
            $AJCB['stats_comment'] = addslashes($row['clmReqCont']);
            $AJCB['regdate'] = $reg_date;
            util::insert_array('auction_jangproduct_claim_baesong', $AJCB);

            // 반품보류 처리!
            $inst = new st11_order($order['number']);
            $inst->returndefer();
            // 반품완료보류 처리!
            $inst = new st11_order($order['number']);
            $inst->returnCpTdefer();

            # 판매자에게 SMS 전송
            send_sms_msg("pricegolf", $order['seller_hphone'], $GLOBALS['site_phone'], $GLOBALS['cfg']['sms']['seller'][8], "order", "", $order['number']);
            # 판매자에게 푸쉬
            if ($GLOBALS['version']['use_fcm']) {
                $firebaseCloudMessageBuilder = new FirebaseCloudMessageBuilder('MypageActivity', array('mode' => 'S', 'stats' => '8', 'title' => $order['title']));
                $message = $firebaseCloudMessageBuilder->addTargetById($order['seller_id'])->build();
                FirebaseCloudMessage::send($message);
            } else {
                GCM_sendEventNotificationToUser($order['seller_id'], "order_sell", "8", "", "", $order['title'], "구매자가 반품을 요청하였습니다. 터치해주세요.");
            }
        }
    }

    /**
     * 구매확정 목록조회
     * $startTime : YYYYMMDDhhmm
     * $endTime : YYYYMMDDhhmm
     */
    public function get_completed_order_list($start = null, $end = null)
    {
        if ($start === null || $end === null) {
            /**
             * 월요일 조회시 목~월 0시 0분
             * 화요일 조회시 금~화 0시 0분
             * 수요일 조회시 월~수 0시 0분
             */
            # 검색종료일시
            $ymd = substr($GLOBALS['YMDHIS'], 0, 10);
            $end_time = strtotime($ymd);
            $start_time = util::getPastWorkday(-2, null, $end_time);

            $start_date = date('Ymd0000', $start_time);
            $end_date = date('Ymd0000', $end_time);
        } else {
            $start_date = $start;
            $end_date = $end;
        }

        $url = "https://api.11st.co.kr/rest/ordservices/completed/{$start_date}/{$end_date}";
        $method = 'GET';

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        $log = array();
        $log['title'] = '구매확정조회';
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # RESPONSE IS NOT XML
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        } # 성공
        elseif ($response_array['order']) {
            if (isset($response_array['order'][0])) {
                $response_array = $response_array['order'];
            } else {
                $response_array = array($response_array['order']);
            }
            $log['result_code'] = 'SUCCESS';
            $log['result_text'] = print_r($response, true);
            util::insert_array('coop_api_log', $log);

            echoLog('11st_order_complete', $response);

            return $response_array;
        } # 내역 없음
        elseif ($response_array['result_code'] == '0') {
            return array();
        } # 기타 오류
        else {
            $log['result_code'] = 'Fault';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        }
    }

    /**
     * 구매확정목록 조회결과 반영
     * $completed_list가 null일 경우 $this->get_return_order_list()로 데이터를 가져옵니다
     */
    public function update_completed_order_list($completed_list = null)
    {
        if ($completed_list == null) $completed_list = $this->get_completed_order_list();
        # NULL 문자열이 반환되는 경우가 있어 추가
        if ($completed_list == 'NULL') return true;

        $reg_date = date('Y-m-d H:i:s');
        for ($i = 0, $end = count($completed_list); $i < $end; ++$i) {
            $row = $completed_list[$i];

            # 주문 데이터 확인
            $sql = "
				SELECT		AJ.*,
							APC.coop_number,
							HM.user_hphone AS seller_hphone
				FROM		(주문테이블) AS AJ
				LEFT OUTER JOIN (제휴상품연동정보테이블) AS APC ON AJ.product_number = APC.product_number
				LEFT OUTER JOIN (회원테이블) AS HM ON AJ.seller_id = HM.user_id
				WHERE		AJ.coop_ordNo = '{$row['ordNo']}'
				AND			AJ.coop_ordSeq = '{$row['ordPrdSeq']}'
				AND			APC.coop_number = '{$row['prdNo']}'
			";
            $order = null;
            list($order) = util::query_to_array($sql);
            # 자사에 데이터가 없을 경우
            if ($order == null) {
                $DA = array();
                $DA['reg_date'] = $reg_date;
                $DA['description'] = "[11번가] 구매확정이 확인되었으나 확인할 수 없는 주문입니다";
                $DA['source'] = print_r($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }
            # 진행상태 확인
            if (in_array($order['product_stats'], array('5', '6', '21', '25'))) continue;
            if ($order['product_stats'] != '4') {
                $DA = array();
                $DA['reg_date'] = $reg_date;
                $DA['description'] = "[11번가] 구매확정이 확인되었으나 주문상태 확인이 필요합니다";
                $DA['source'] = print_r($row, true);
                util::insert_array('developer_alert', $DA);
                continue;
            }
            # 논현점 판매건은 정산완료로 변경
            if ($order['seller_id'] == 'pricegolf') {
                $sql_update = "
                    UPDATE (주문테이블) SET
                        product_stats = '6',
                        decision_date = '{$reg_date}',
                        jungsanhwakjung_date = '{$reg_date}',
                        ini_reg_status = '0'
                    WHERE number = {$order['number']}
			    ";
                $update_stats = 6;
            } else {
                $sql_update = "
                    UPDATE (주문테이블) SET
                        product_stats = '5',
                        decision_date = '{$reg_date}',
                        ini_reg_status = '0'
                    WHERE number = {$order['number']}
			    ";
                $update_stats = 5;
            }
            query($sql_update);

            # auction_jangproduct_log
            $AJL = array();
            $AJL['order_no'] = $order['number'];
            $AJL['user_id'] = '11st';
            $AJL['user_ip'] = '11st';
            $AJL['gubun'] = $update_stats;
            $AJL['reg_date'] = $reg_date;
            util::insert_array('auction_jangproduct_log', $AJL);
        }
    }


    public function getDeliveryInfo($jp_numbers = null)
    {
        $WHERE = array();
        if (is_null($jp_numbers)) {
            $WHERE[] = "AJ.site = '11st'";
            $WHERE[] = "AJ.product_stats = 4";
        } elseif (is_array($jp_numbers)) {
            $jp_numbers = imiplode(',', $jp_numbers);
            $WHERE[] = "AJ.number IN ({$jp_numbers})";
        } else {
            $WHERE[] = "AJ.number IN ({$jp_numbers})";
        }

        $WHERE = implode(' AND ', $WHERE);
        $sql = "
            SELECT      AJ.number,
                        AJ.coop_ordNo,
                        AJ.coop_ordSeq,
                        ST.number AS `st_number`,
                        ST.tracking_stats,
                        ST.complete_date,
                        ST.last_tracking_date
            FROM        (주문테이블) AJ
            LEFT OUTER JOIN auction_jangproduct_sweettracker AS ST ON AJ.number = ST.jp_number
            WHERE       {$WHERE}
        ";
        $data = util::query_to_array($sql);
        $order_numbers = util::array_column($data, 'coop_ordNo');
        $order_numbers = array_values(array_unique($order_numbers));

        # 100개씩 조회가능하여 각각 나누어 조회
        $order_numbers = array_chunk($order_numbers, 100, false);
        $response_array_merge = array();
        for ($i = 0, $end = count($order_numbers); $i < $end; ++$i) {
            $order_numbers100 = $order_numbers[$i];
            $order_numbers100 = implode(',', $order_numbers100);

            $url = "http://api.11st.co.kr/rest/claimservice/orderlistall/{$order_numbers100}";
            $method = 'GET';
            $response = parent::request($url, $method);
            $response_array = parent::xml_to_array($response);
            if (!empty($response_array['order'])) {
                if (!isset($response_array['order'][0])) $response_array['order'] = array($response_array['order']);
                $response_array_merge = array_merge($response_array_merge, $response_array['order']);
            }
        }
        return array('order' => $data, 'response' => $response_array_merge);
    }

    public function updateDeliveryInfo($data = null)
    {
        if (empty($data)) $data = $this->getDeliveryInfo();


        if ($_COOKIE['ad_id'] == 'iqrash') echoDev($data);
        $AJST = array();
        $today = substr($GLOBALS['YMDHIS'], 0, 10);
        for ($i = 0, $end = count($data['order']); $i < $end; ++$i) {
            $order_row = $data['order'][$i];
            $response_row = util::searchByKeyValue($data['response'], 'ordNo', $order_row['coop_ordNo']);
            list($response_row) = util::searchByKeyValue($response_row, 'ordPrdSeq', $order_row['coop_ordSeq'], 1);
            if ($response_row === null) continue;

            $is_complete = in_array($response_row['ordPrdStatNm'], array('배송완료', '수취확인', '구매확정'));

            # 신규입력
            if ($order_row['st_number'] === NULL) {
                $AJST_row = array();
                $AJST_row['jp_number'] = $order_row['number'];
                $AJST_row['tracking_stats'] = $response_row['ordPrdStatNm'];
                if ($is_complete)
                    $AJST_row['complete_date'] = $today;
                else
                    $AJST_row['complete_date'] = '0000-00-00 00:00:00';
                $AJST_row['last_tracking_date'] = $GLOBALS['YMDHIS'];
                $AJST[] = $AJST_row;
                continue;
                # 갱신
            } else {
                $update_columns_temp = array();
                $update_columns = array();
                $update_columns['last_tracking_date'] = $GLOBALS['YMDHIS'];
                if ($order_row['tracking_stats'] != $response_row['ordPrdStatNm']) {
                    if ($order_row['tracking_stats'] == '배송완료' && $response_row['ordPrdStatNm'] == '배송중') continue;
                    #echoDev('###',$order_row,$response_row);
                    $was_completed = in_array($order_row['tracking_stats'], array('배송완료', '수취확인', '구매확정'));
                    $update_columns['tracking_stats'] = $response_row['ordPrdStatNm'];

                    if (!$was_completed && $is_complete) $update_columns['complete_date'] = $today;
                    if ($order_row['complete_date'] > 0) unset($update_columns['complete_date']);

                    foreach ($update_columns AS $k => $v) $update_columns_temp[] = "`{$k}` = '{$v}'";
                    $update_columns_temp = implode(',', $update_columns_temp);
                    $sql_update = "
                    UPDATE auction_jangproduct_sweettracker SET
                        {$update_columns_temp}
                    WHERE number = '{$order_row['st_number']}'
                    ";
                    if ($GLOBALS['test']) echoDev($sql_update);
                    else query($sql_update);
                }
            }
        }
        if (count($AJST)) {
            if ($GLOBALS['test'])
                echoDev('INSERT INTO auction_jangproduct_sweettracker', $AJST);
            else
                util::insert_multi_array('auction_jangproduct_sweettracker', $AJST);
        }
    }

}