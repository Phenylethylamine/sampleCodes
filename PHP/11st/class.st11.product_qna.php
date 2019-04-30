<?php
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.php");

/**
 * 상품 QNA 처리
 */
class st11_product_qna extends st11
{
    /**
     * 어제~오늘 사이의 11번가 미답변 상품 QnA 목록을 조회하여
     * 신규 목록을 자사 DB에 반영합니다
     * 수정된 문의가 있으면 Update로 반영됩니다
     */
    public function apply_product_qna_list()
    {
        # 최근 1주일간 미답변 QnA 목록 조회
        $st11_qna_list = $this->get_product_qna_list();
        if (is_array($st11_qna_list) == false) return false;

        # 시간순 DESC 순서이므로, ASC 순서로 변경합니다
        $st11_qna_list = array_reverse($st11_qna_list);

        # 11번가 상품번호목록 추출
        $st11_coop_product_number_list = array();
        for ($i = 0, $end = count($st11_qna_list); $i < $end; ++$i) {
            $row = $st11_qna_list[$i];
            $st11_coop_product_number_list[] = "'" . $row['brdInfoClfNo'] . "'";
        }
        # 중복값 제거
        $st11_coop_product_number_list = array_unique($st11_coop_product_number_list);
        $st11_coop_product_number_list = implode(',', $st11_coop_product_number_list);
        $sql_product = "
			SELECT		APC.coop_number,
						APC.product_number,
						AP.product_code,
						AP.id
			FROM		(제휴상품연동정보테이블) AS APC
			LEFT OUTER JOIN (상품테이블) AS AP ON APC.product_number = AP.number
			WHERE		APC.coop_number IN ({$st11_coop_product_number_list})
		";
        # 상품정보 추출
        $product_list = util::query_to_array($sql_product);

        # QnA DB 조회
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $sql_select_qna_list = "
			SELECT	A.*
			FROM	auction_product_qna AS A
			WHERE	A.reg_date > '{$yesterday}'
			AND		A.site = '11st'
		";
        $db_qna_list = util::query_to_array($sql_select_qna_list);

        # 11번가 QnA목록을 순회
        $insert_rows = array();
        $reg_date = date('Y-m-d H:i:s');
        $push_id_list = array();
        for ($i = 0, $end = count($st11_qna_list); $i < $end; ++$i) {
            $row = &$st11_qna_list[$i];

            # DB에 입력된 문의인지 확인
            $searched = null;
            list($searched) = util::searchByKeyValue($db_qna_list, 'coop_qnaNo', $row['brdInfoNo'], 1);
            # 이미 DB에 저장된 문의일 경우
            if ($searched != null) {
                # 문의제목,문의내용,문의코드를 비교해서 수정여부를 판단합니다
                $is_updated = (
                    $row['brdInfoSbjct'] != $searched['title']
                    || $row['brdInfoCont'] != $searched['comment']
                    || $row['qnaDtlsCd'] != $searched['qna_type']
                );
                # 내용이 변경되었으면 수정합니다.
                if ($is_updated) {
                    $title = addslashes($row['brdInfoSbjct']);
                    $comment = addslashes($row['brdInfoCont']);
                    $qna_type = addslashes($row['qnaDtlsCd']);
                    $sql_update = "
						UPDATE auction_product_qna SET
							`title` = '{$title}',
							`comment` = '{$comment}',
							`qna_type` = '{$qna_type}'
						WHERE number = {$searched['number']}
					";
                    query($sql_update);
                }
                continue;
            } # DB에 저장된 문의가 아닐 경우
            else {
                # 자사상품번호,자사상품코드를 찾습니다
                $searched_product = null;
                list($searched_product) = util::searchByKeyValue($product_list, 'coop_number', $row['brdInfoClfNo'], 1);
                # 자사 상품 데이터가 없을 경우 개발자에게 메일을 보냅니다
                if ($searched_product == null) {
                    $DA['reg_date'] = date('Y-m-d H:i:s');
                    $DA['description'] = '[11번가] 11번가 상품문의가 조회되었으나 확인할 수 없는 상품입니다';
                    $DA['source'] = print_r($row, true);
                    insert_array('developer_alert', $DA);
                    continue;
                }

                # INSERT_ROW를 생성합니다
                # ( `product_number`,`product_code`,`user_id`,`reg_date`,`title`,`comment`,`qna_type`,`hidden`,`site`,`coop_qnaNo`,`coop_qnaType` )
                $insert_row = array();
                $insert_row[] = $searched_product['product_number']; #product_number
                $insert_row[] = $searched_product['product_code']; #product_code
                $insert_row[] = "{$row['memID']}_11st"; #user_id
                $insert_row[] = $reg_date; #reg_date
                $insert_row[] = addslashes($row['brdInfoSbjct']); #title
                $insert_row[] = addslashes($row['brdInfoCont']); #comment
                $insert_row[] = $row['qnaDtlsCd']; #qna_type
                $insert_row[] = 'N'; #hidden
                $insert_row[] = '11st'; #site
                $insert_row[] = $row['brdInfoNo']; #coop_qnaNo
                $insert_row[] = 'board'; #coop_qnaType

                $insert_rows[] = "('" . implode("','", $insert_row) . "')";

                $push_id_list[] = $searched_product['id'];
            }
        }
        # QnA DB 입력
        if (count($insert_rows)) {
            $sql_insert_qna = "
				INSERT INTO auction_product_qna
				(`product_number`,`product_code`,`user_id`,`reg_date`,`title`,`comment`,`qna_type`,`hidden`,`site`,`coop_qnaNo`,`coop_qnaType`)
				VALUES
			";
            $sql_insert_qna .= implode(',', $insert_rows);
            query($sql_insert_qna);
        }
        # PUSH
        if (count($push_id_list)) {
            $push_id_list = array_unique($push_id_list);
            if ($GLOBALS['version']['use_fcm']) {
                $firebaseCloudMessageBuilder = new FirebaseCloudMessageBuilder('QnaActivity', array('mode' => 'Q'));
                $message = $firebaseCloudMessageBuilder->addTargetById($push_id_list)->build();
                FirebaseCloudMessage::send($message);
            } else {
                for ($i = 0, $end = count($push_id_list); $i < $end; ++$i) {
                    GCM_sendEventNotificationToUser($push_id_list[$i], "qna", "seller", "", "", "상품문의 알림", "판매자님 상품 문의가 들어왔습니다.");
                }
            }

        }
    }

    /**
     * 구매자가 문의한 상품 QnA 목록을 조회 합니다. 조회 기간은 최대 7일(1주일)입니다.
     * $param에는 3가지 키가 입려될 수 있습니다
     * startTime : 검색시작일 YYYYMMDD 기본값 1일전
     * endTime : 검색종료일 YYYYMMDD 기본값 오늘
     * answerStatus : 처리여부 00(전체조회) 01(답변완료) 02(미답변조회,기본값)
     */
    public function get_product_qna_list($param = array())
    {
        if ($this->test) $this->test_log[] = 'get_product_qna_list';

        if ($param['startTime']) $startTime = $param['startTime'];
        else $startTime = date('Ymd', strtotime('-1 day'));

        if ($param['endTime']) $endTime = $param['endTime'];
        else $endTime = date('Ymd');

        if ($param['answerStatus']) $answerStatus = $param['answerStatus'];
        else $answerStatus = '02';

        $url = "http://api.11st.co.kr/rest/prodqnaservices/prodqnalist/{$startTime}/{$endTime}/{$answerStatus}";
        $method = 'GET';


        $response = self::request($url, $method);
        $response_array = self::xml_to_array($response);

        $log = array();
        $log['title'] = '상품QNA조회';
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        if ($response_array == false) {
            $log['result_code'] = 'error';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            util::insert_array('coop_api_log', $log);
            return false;
        } # 검색된 대상이 없습니다
        elseif ($response_array['result_code'] == '500') {
            return true;
        } else {
            $log['result_code'] = 'SUCCESS';
            util::insert_array('coop_api_log', $log);

            if (isset($response_array['productQna'][0]) == false) {
                $response_array = array($response_array['productQna']);
            } else {
                $response_array = $response_array['productQna'];
            }
            return $response_array;
        }
        return $response_array;
    }

    /*
     * 11번가 상품 qna에 답변을 등록합니다
     * $qna_number : numeric,array : auction_product_qna.number
     */
    static function apply_product_qna_answer($qna_number)
    {
        # qna 번호가 아니면
        if (is_numeric($qna_number) == false) return false;
        $sql = "
			SELECT	APQ.reply_comment,
					APQ.coop_qnaNo,
					APC.coop_number,
					APC.product_number
			FROM	auction_product_qna AS APQ
			LEFT OUTER JOIN (제휴상품연동정보테이블) AS APC
			    ON APQ.product_number = APC.product_number
			    AND APC.coop_name = '11st'
			WHERE	APQ.number = {$qna_number}
			AND		APQ.site = '11st'
		";
        list($data) = util::query_to_array($sql);
        # 데이터가 없으면
        if ($data == null) return false;
        # 답변이 없으면
        elseif ($data['reply_comment'] == '') return false;
        # xml 작성
        $inst = new string_filter();
        $reply = $inst->filtering_telno($data['reply_comment']);
        $xml = "<ProductQna><answerCont>{$reply}</answerCont></ProductQna>";
        $url = "http://api.11st.co.kr/rest/prodqnaservices/prodqnaanswer/{$data['coop_qnaNo']}/{$data['coop_number']}";
        $method = 'PUT';
        # REQUEST
        $response = self::request($url, $method, $xml);
        $response_array = self::xml_to_array($response);

        #echoDev( $xml,$response,$response_array );
        #return;

        # 로그준비
        $log = array();
        $log['title'] = 'Q&A답변';
        $log['jp_number'] = $data['product_number'];
        $log['ord_no'] = $data['coop_qnaNo'];
        $log['api_url'] = '11st';
        $log['reg_date'] = date('Y-m-d H:i:s');

        # 통신오류
        if ($response_array == false) {
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = $response;
            $return = false;
        } # 성공
        elseif ($response_array['resultCode'] == '200') {
            $log['result_code'] = 'SUCCESS';
            $return = true;
        } # 실패
        else {
            $log['result_code'] = 'FAIL';
            $log['result_msg'] = $response_array['message'];
            $log['result_text'] = print_r($response_array, true);
            $return = false;
        }
        # 로그 입력
        util::insert_array('coop_api_log', $log);
        return $return;
    }
}