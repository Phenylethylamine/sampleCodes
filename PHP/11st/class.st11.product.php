<?php
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.php");

/**
 * 상품등록 : POST, http://api.11st.co.kr/rest/prodservices/product
 * 상품수정 : PUT, http://api.11st.co.kr/rest/prodservices/product/[상품번호]
 */
class st11_product extends st11
{
    /**
     * 인스턴트 생성시 상품번호 입력
     */
    public function __construct($product_number)
    {
        $this->product_number = $product_number;
    }

    /**
     * 상품등록 실행
     */
    public function add_product()
    {
        $product_data = $this->get_product_data();

        if ($product_data === false) {
            if ($GLOBALS['print_process']) echoDev('상품정보를 확인할 수 없습니다', $product_data);
            return false;
        }

        # 11번가 상품번호가 있으면 새로 등록하지 않습니다
        if ($product_data['coop_number'] != '' && $product_data['coop_number'] != '999') {
            return false;
        }
        # 출고지, 반품주소지 등록안됨
        if (
            $product_data['return_address_value'] == '' ||
            $product_data['out_address_value'] == ''
        ) {
            if ($GLOBALS['print_process']) echoDev('출고지,반품주소지 등록안됨', $product_data);
            return false;
        }
        # 제휴 등록 거부
        if (
            $product_data['refusal_return'] == '1' ||
            $product_data['refusal_out'] == '1' ||
            $product_data['regadd_interpark'] != 'Y'
        ) {
            if ($GLOBALS['print_process']) echoDev('제휴등록거부', $product_data);
            return false;
        }

        $url = 'http://api.11st.co.kr/rest/prodservices/product';
        $xml = $this->make_add_xml();
        $response = $this->request($url, 'POST', $xml);
        $response_array = $this->xml_to_array($response);

        if ($_COOKIE['ad_id'] == 'qudghk1219') echoDev($response_array);

        $reg_date = date('Y-m-d H:i:s');
        # 로그 준비(공통)
        $log = array();
        $log['title'] = '상품등록';
        $log['jp_number'] = $product_data['number'];
        if ($_COOKIE['ad_id']) $log['reg_id'] = $_COOKIE['ad_id'];
        elseif ($GLOBALS['mem_id']) $log['reg_id'] = $GLOBALS['mem_id'];
        $log['api_url'] = '11st';
        $log['reg_date'] = $reg_date;

        # RESPONSE IS NOT XML
        if ($response_array === false) {
            # RESPONSE가 HTML 형식일 경우 11번가 서버점검입니다
            # 이 경우엔 제휴연동 데이터와 로그를 남기지 않습니다
            if (is_numeric(strpos($response, '<!DOCTYPE'))) {
                return false;
            }
            # 로그 준비
            $log['result_code'] = 'ERROR';
            $log['result_msg'] = 'RESPONSE IS NOT XML';
            $log['result_text'] = print_r($response, true);

            # 제휴연동 데이터 입력
            if ($product_data['apc_number'] == '') {
                $apc_array = array();
                $apc_array['product_number'] = $product_data['number'];
                $apc_array['coop_name'] = '11st';
                $apc_array['coop_number'] = '999';
                $apc_array['coop_stats'] = 'error';
                $apc_array['coop_reg_date'] = $reg_date;
                util::insert_array('auction_product_coop', $apc_array);
            }

            # 로그 입력
            util::insert_array('coop_api_log', $log);
            return false;
        } # 등록성공
        elseif ($response_array['resultCode'] == '200') {

            # 로그 준비
            $log['result_msg'] = $response_array['productNo'];
            $log['result_code'] = 'SUCCESS';

            # 제휴연동 데이터 입력
            if ($product_data['coop_number'] == '999') {
                $sql = "
					UPDATE (제휴상품연동정보테이블) SET
						coop_number = '{$response_array['productNo']}',
						coop_stats = 'onsale',
						coop_reg_date = '{$reg_date}'
					WHERE number = {$product_data['apc_number']}
				";
                query($sql);
            } else {
                $apc_array = array();
                $apc_array['product_number'] = $product_data['number'];
                $apc_array['coop_name'] = '11st';
                $apc_array['coop_number'] = $response_array['productNo'];
                $apc_array['coop_stats'] = 'onsale';
                $apc_array['coop_reg_date'] = $reg_date;
                util::insert_array('auction_product_coop', $apc_array);
            }

            # 옵션 상품이라면 옵션 데이터 입력
            if ($product_data['oid'] != '') {
                # 옵션수정을 위해 coop_number 데이터 추가
                $this->product_data['coop_number'] = $response_array['productNo'];
                $edit_option_result = $this->edit_product_option();
                if ($edit_option_result == false) {
                    $this->stop_product();
                    return false;
                }
            }

            # 로그 입력
            util::insert_array('coop_api_log', $log);

            return true;
        } # 양식오류
        else {
            # 로그 준비
            $log['result_code'] = 'FAIL';
            $log['result_msg'] = $response_array['message'];
            $log['result_text'] = $response;

            # 제휴연동 데이터 입력
            if ($product_data['apc_number'] == '') {
                $apc_array = array();
                $apc_array['product_number'] = $product_data['number'];
                $apc_array['coop_name'] = '11st';
                $apc_array['coop_number'] = '999';
                $apc_array['coop_stats'] = 'error';
                $apc_array['coop_reg_date'] = $reg_date;
                util::insert_array('auction_product_coop', $apc_array);
            } else {
                $sql_update = "
					UPDATE (제휴상품연동정보테이블) SET
						coop_number = '999',
						coop_stats = 'error',
						coop_reg_date = '{$reg_date}'
					WHERE number = {$product_data['apc_number']}
				";
                query($sql_update);
            }

            # 로그 입력
            util::insert_array('coop_api_log', $log);

            return false;
        }
    }

    /*
     * 11번가 상품 수정
     */
    public function edit_product()
    {
        $product_data = $this->get_product_data();
        # 11번가 상품번호가 없으면 작업 안함
        if ($product_data['coop_number'] == '' || $product_data['coop_number'] == '999') {
            return true;
        }
        # 판매금지된 상품은 수정 안함
        if ($product_data['coop_stats'] == 'prohibited') {
            return true;
        }

        # 11번가 상품 상태 확인
        $st11_data = $this->prodmarket();

        # 등록 거부
        if (
            $product_data['refusal_return'] == '1' ||
            $product_data['refusal_out'] == '1' ||
            $product_data['regadd_interpark'] != 'Y'
        ) {
            if (in_array($st11_data['selStatCd'], array('103', '104'))) {
                return $this->stop_product('등록거부');
            } else {
                return true;
            }
        }
        # 판매금지된 상품은 수정 안함
        if ($st11_data['selStatCd'] == '108') {
            $sql = "
				UPDATE (제휴상품연동정보테이블) SET
					coop_stats = 'prohibited'
				WHERE product_number = {$product_data['number']}
			";
            query($sql);
            return true;
        }
        # 판매중 상품 : 상품수정->11번가에 판매중지 상태라면 판매중으로 전환합니다
        if ($product_data['product_stats'] == '0') {
            $url = "http://api.11st.co.kr/rest/prodservices/product/{$product_data['coop_number']}";
            $xml = $this->make_add_xml();
            # xml 작성 실패
            if ($xml == false) {
                return $this->stop_product('XML작성실패');
            }
            $response = $this->request($url, 'PUT', $xml);
            $response_array = $this->xml_to_array($response);

            $reg_date = date('Y-m-d H:i:s');

            # 로그 준비(공통)
            $log_array = array();
            $log_array['title'] = '상품수정';
            $log_array['jp_number'] = $product_data['number'];
            if ($_COOKIE['ad_id']) $log_array['reg_id'] = $_COOKIE['ad_id'];
            elseif ($GLOBALS['mem_id']) $log_array['reg_id'] = $GLOBALS['mem_id'];
            $log_array['api_url'] = '11st';
            $log_array['reg_date'] = $reg_date;

            # RESPONSE IS NOT XML
            if ($response_array === false) {
                $log_array['result_code'] = 'ERROR';
                $log_array['result_msg'] = 'RESPONSE IS NOT XML';
                $log_array['result_text'] = $response;
                util::insert_array('coop_api_log', $log_array);

                # 판매중지
                if (in_array($st11_data['selStatCd'], array('103', '104'))) return $this->stop_product('수정오류');
                return true;
            } # 수정성공
            elseif ($response_array['resultCode'] == '200') {
                # 옵션 수정
                $edit_option_result = $this->edit_product_option();
                if ($edit_option_result == false) {
                    $log_array['result_code'] = 'FAIL';
                    $log_array['result_msg'] = '옵션수정오류';
                    $log_array['result_text'] = $this->error;
                    util::insert_array('coop_api_log', $log_array);

                    if (in_array($st11_data['selStatCd'], array('103', '104'))) return $this->stop_product('옵션수정오류');
                    return true;
                }

                # 로그 준비
                $log_array['result_msg'] = $response_array['productNo'];
                $log_array['result_code'] = 'SUCCESS';
                util::insert_array('coop_api_log', $log_array);
                # 11번가에 판매중 상태가 아니면 판매중으로 변경합니다
                if (!in_array($st11_data['selStatCd'], array('103', '104'))) {
                    $this->restart_product();
                } else {
                    $sql_update = "
						UPDATE (제휴상품연동정보테이블) SET
							coop_stats = 'onsale',
							coop_mod_date = NOW()
						WHERE number = {$product_data['apc_number']}
					";
                    query($sql_update);
                }
                return true;
            } # 판매금지 상품은 수정 불가
            elseif (is_numeric(strpos('상태[108]', $response_array['message']))) {
                # auction_product_coop에 상태 반영
                $sql_update = "
					UPDATE (제휴상품연동정보테이블) SET
						coop_stats = 'prohibited'
					WHERE number = {$product_data['apc_number']}
				";
                query($sql_update);
            } # 기타오류
            else {
                # 로그 준비
                $log_array['result_code'] = 'FAIL';
                $log_array['result_msg'] = $response_array['message'];
                $log_array['result_text'] = $response;
                util::insert_array('coop_api_log', $log_array);
                # 11번가에 판매중 상태라면 판매중지
                if (in_array($st11_data['selStatCd'], array('103', '104'))) {
                    return $this->stop_product('수정양식오류');
                }
                return true;
            }
        } # 판매종료 상품 : 11번가에 판매중이라면 종료합니다
        else {
            if (in_array($st11_data['selStatCd'], array('103', '104'))) {
                return $this->stop_product();
            } elseif ($product_data['coop_stats'] == 'onsale') {
                $sql = "
					UPDATE (제휴상품연동정보테이블) SET
						coop_stats = 'stop',
						coop_mod_date = NOW()
					WHERE	product_number = {$product_data['number']}
					AND		coop_number = '{$product_data['coop_number']}'
					AND		coop_name = '11st'
				";
                query($sql);
                return true;
            } else {
                return true;
            }
        }
    }

    /**
     * 상품 판매 중지 처리
     * $reason : 종료 성공시 coop_api_log에 종료 사유를 입력합니다
     */
    public function stop_product($reason = '')
    {
        $product_data = $this->get_product_data();
        $url = "http://api.11st.co.kr/rest/prodstatservice/stat/stopdisplay/{$product_data['coop_number']}";
        $response = $this->request($url, 'PUT');
        $response_array = $this->xml_to_array($response);

        $reg_date = date('Y-m-d H:i:s');
        # 로그 준비(공통)
        $log_array = array();
        $log_array['title'] = '상품종료';
        $log_array['jp_number'] = $product_data['number'];
        if ($_COOKIE['ad_id']) $log_array['reg_id'] = $_COOKIE['ad_id'];
        elseif ($GLOBALS['mem_id']) $log_array['reg_id'] = $GLOBALS['mem_id'];
        $log_array['api_url'] = '11st';
        $log_array['reg_date'] = $reg_date;

        # RESPONSE IS NOT XML
        if ($response_array === false) {
            $DA = array();
            $DA['reg_date'] = $reg_date;
            $DA['description'] = "[11번가] 상품종료실패 {$product_data['number']}";
            $DA['source'] = $response;
            util::insert_array('developer_alert', $DA);

            $log_array['result_code'] = 'ERROR';
            $log_array['result_msg'] = 'RESPONSE IS NOT XML';
            $log_array['result_text'] = print_r($response, true);
            util::insert_array('coop_api_log', $log_array);

            return false;
        } # 종료성공
        elseif ($response_array['resultCode'] == '200') {
            $log_array['result_msg'] = $response_array['productNo'];
            $log_array['result_code'] = 'SUCCESS';
            if ($reason) $log_array['result_text'] = $reason;
            util::insert_array('coop_api_log', $log_array);

            # 제휴연동 데이터 업데이트
            $sql_update_apc = "
				UPDATE (제휴상품연동정보테이블) SET
					coop_stats = 'stop',
					coop_mod_date = NOW()
				WHERE	product_number = {$product_data['number']}
				AND		coop_number = {$product_data['coop_number']}
			";
            query($sql_update_apc);

            return true;
        } # 판매중이 아님
        elseif (is_numeric(strpos($response_array['message'], '판매중/전시전인 상품만 판매중지가 가능합니다.'))) {
            $log_array['result_msg'] = $response_array['message'];
            $log_array['result_code'] = 'unnecessary';
            if ($reason) $log_array['result_text'] = $reason;
            util::insert_array('coop_api_log', $log_array);

            # 제휴연동 데이터 업데이트
            $sql_update_apc = "
				UPDATE (제휴상품연동정보테이블) SET
					coop_stats = 'stop',
					coop_mod_date = NOW()
				WHERE	product_number = {$product_data['number']}
				AND		coop_number = {$product_data['coop_number']}
			";
            query($sql_update_apc);

            return true;
        } # 양식오류
        else {
            $DA = array();
            $DA['reg_date'] = $reg_date;
            $DA['description'] = "[11번가] 상품종료실패 {$product_data['number']}";
            $DA['source'] = $response;
            util::insert_array('developer_alert', $DA);

            $log_array['result_code'] = 'FAIL';
            $log_array['result_msg'] = $response_array['message'];
            $log_array['result_text'] = $response;
            util::insert_array('coop_api_log', $log_array);

            return false;
        }
    }

    /**
     * 상품 옵션 수정
     */
    public function edit_product_option()
    {
        $product_data = $this->get_product_data();
        if ($product_data['oid'] == '') {
            return true;
            $xml = '<Product></Product>';
        } else {
            $xml_data = array();

            # 옵션 기본 설정
            $xml_data['optSelectYn'] = 'Y';
            $xml_data['txtColCnt'] = '1';
            $xml_data['prdExposeClfCd'] = '01';
            $xml_data['colTitle'] = '상품옵션';

            # 옵션 데이터 확인
            $sql_get_option = "
				SELECT	*
				FROM	(옵션정보테이블)
				WHERE	oid = '{$product_data['oid']}'
				AND		product_number = '{$product_data['number']}'
			";
            $option_data = util::query_to_array($sql_get_option);


            $xml_data['ProductOption'] = array();
            for ($i = 0, $end = count($option_data); $i < $end; ++$i) {
                $each = $option_data[$i];
                $temp_array = array();
                $temp_array['colOptPrice'] = $each['add_price'];

                # 사용불가 문자 제거
                $option_name = "[{$each['pid']}] {$each['id']}";
                $denied_chars = '\'"%&<>#\\,|';
                $option_name = str_replace(str_split($denied_chars), '', $option_name);
                $temp_array['colValue0'] = $option_name;

                $temp_array['colCount'] = $each['jaego'];
                $temp_array['colSellerStockCd'] = $each['number'];
                $temp_array['useYn'] = ($each['jaego'] > 0) ? 'Y' : 'N';

                $xml_data['ProductOption'][] = $temp_array;
            }

            $xml = "<Product>" . parent::array_to_xml($xml_data) . "</Product>";
        }
        $url = "http://api.11st.co.kr/rest/prodservices/updateProductOption/{$product_data['coop_number']}";
        $response = parent::request($url, 'POST', $xml);
        $response_array = parent::xml_to_array($response);

        if ($response_array == false) {
            $this->error = $response;
            return false;
        } elseif ($response_array['resultCode'] == '200') {
            return true;
        } else {
            $this->error = $response;
            return false;
        }
    }

    public function edit_product_etc_option()
    {
        $product_data = $this->get_product_data();
        $etc_option = explode(PHP_EOL, $product_data['etc_option']);

        $xml_data = array();
        $xml_data['ProductComponent'] = array();

        for ($i = 0, $end = count($etc_option); $i < $end; ++$i) {
            $row = explode(':', trim($etc_option[$i]));
            if ($row[0] == '' || $row[1] == '') {
                $error = true;
                break;
            }
            $row_data = array();
            $row_data['addCompPrc'] = $row[1];
            $row_data['addPrdGrpNm'] = '추가옵션선택';
            $row_data['addUseYn'] = 'Y';
            $row_data['compPrdNm'] = $row[0];
            $row_data['compPrdQty'] = '999';
            $xml_data['ProductComponent'][] = $row_data;
        }
        if ($error || true) {
            $xml = '<Product></Product>';
        } else {
            $xml = '<Product>' . parent::array_to_xml($xml_data) . '</Product>';
        }

        $url = "http://api.11st.co.kr/rest/prodservices/updateProductComponent/{$product_data['coop_number']}";
        $response = parent::request($url, 'POST', $xml);

        return $response;
    }

    /**
     * 상품 판매 재시작 처리
     */
    public function restart_product()
    {
        $product_data = $this->get_product_data();
        $url = "http://api.11st.co.kr/rest/prodstatservice/stat/restartdisplay/{$product_data['coop_number']}";
        $response = $this->request($url, 'PUT');
        $response_array = $this->xml_to_array($response);

        $reg_date = date('Y-m-d H:i:s');
        # 로그 준비(공통)
        $log_array = array();
        $log_array['title'] = '상품재시작';
        $log_array['jp_number'] = $product_data['number'];
        if ($_COOKIE['ad_id']) $log_array['reg_id'] = $_COOKIE['ad_id'];
        elseif ($GLOBALS['mem_id']) $log_array['reg_id'] = $GLOBALS['mem_id'];
        $log_array['api_url'] = '11st';
        $log_array['reg_date'] = $reg_date;

        # RESPONSE IS NOT XML
        if ($response_array === false) {
            $log_array['result_code'] = 'ERROR';
            $log_array['result_msg'] = 'RESPONSE IS NOT XML';
            $log_array['result_text'] = $response;
            util::insert_array('coop_api_log', $log_array);
            return false;
        } # 종료성공
        elseif ($response_array['resultCode'] == '200') {
            # 로그 준비
            $log_array['result_msg'] = $response_array['productNo'];
            $log_array['result_code'] = 'SUCCESS';
            util::insert_array('coop_api_log', $log_array);

            # 제휴연동 데이터 업데이트
            $sql_update_apc = "
				UPDATE (제휴상품연동정보테이블) SET
					coop_stats = 'onsale',
					coop_mod_date = NOW()
				WHERE	product_number = {$product_data['number']}
				AND		coop_number = {$product_data['coop_number']}
			";
            query($sql_update_apc);
            return true;
        } # 양식오류
        else {
            # 로그 준비
            $log_array['result_code'] = 'FAIL';
            $log_array['result_msg'] = $response_array['message'];
            $log_array['result_text'] = $response;
            util::insert_array('coop_api_log', $log_array);
            return false;
        }

        return $return;
    }

    /**
     * 객체에 저장된 상품번호로 상품정보를 추출합니다
     */
    public function get_product_data()
    {
        # 기존 데이터가 있으면 재사용
        if (isset($this->product_data)) return $this->product_data;
        # 상품번호 검증
        if (is_numeric($this->product_number) == false) return false;
        # 쿼리
        $sql = "
			SELECT		AP.*,
						( SELECT option_value FROM happy_member_option WHERE option_field = 'susuryo' AND user_id = AP.id ) AS susuryo,
						APE.ext01t, APE.ext01,
						APE.ext02t, APE.ext02,
						APE.ext03t, APE.ext03,
						APE.ext04t, APE.ext04,
						APE.ext05t, APE.ext05,
						M.model_name AS model_name_text,
						CRA.coop_value AS return_address_value,
						CRA.refusal AS refusal_return,
						COA.coop_value AS out_address_value,
						COA.refusal AS refusal_out,
						APC.number AS apc_number,
						APC.coop_number,
						APC.coop_stats,
						APC.number AS apc_number,
						HM.extra16 AS hm_extra16
			FROM		auction_product AS AP
			LEFT OUTER JOIN (상품정보확장테이블) AS APE ON AP.number = APE.product_number
			LEFT OUTER JOIN (모델테이블) AS M ON AP.model_name = M.model_sub_num
			LEFT OUTER JOIN (회원테이블) AS HM ON AP.id = HM.user_id
			LEFT OUTER JOIN (제휴상품연동정보테이블) AS APC ON AP.number = APC.product_number AND APC.coop_name = '11st'
			LEFT OUTER JOIN (제휴반품주소지정보테이블)AS CRA ON CRA.user_id = HM.user_id AND CRA.coop_name = '11st.return_address'
			LEFT OUTER JOIN (제휴반품주소지정보테이블)AS COA ON COA.user_id = HM.user_id AND COA.coop_name = '11st.out_address'
			WHERE		AP.number = {$this->product_number}
		";
        $result = query($sql);
        $this->product_data = mysql_fetch_assoc($result);
        if ($this->product_data === false) return false;

        calBaroPrice($this->product_data);

        return $this->product_data;
    }

    /**
     * 상품등록 XML을 반환합니다
     */
    public function make_add_xml()
    {
        /*
        [스포츠용품 등록항목]
        색상
        품명 및 모델명
        크기, 중량
        동일모델의 출시년월
        제조국
        제품구성
        A/S 책임자와 전화번호
        제조자, 수입품의 경우 수입자를 함께 표기 (병행수입의 경우 병행수입 여부로 대체 가능)
        재질
        품질보증기준
        상품별 세부 사양
         */
        $product_data = $this->get_product_data();

        $xml_data = array();
        # 판매방식 : 01(고정가판매), 05(중고판매)
        if ($product_data['product_sangtae'] == '1') {
            $xml_data['selMthdCd'] = '05';
        } else {
            $xml_data['selMthdCd'] = '01';
        }
        # 카테고리
        $xml_data['dispCtgrNo'] = $this->make_11st_category();
        # 카테고리가 없으면 등록불가 상품 처리
        if ($xml_data['dispCtgrNo'] == '') {
            return false;
        }
        # 서비스 상품 코드 : 01(일반배송상품)
        $xml_data['prdTypCd'] = '01';
        /*
        # H.S Code
        $xml_data['hsCode'] = $this->make_hs_code();
        */
        # 상품명
        $xml_data['prdNm'] = $this->make_11st_product_name();
        # 브랜드
        $xml_data['brand'] = htmlspecialchars($this->product_data['brand_name']);
        # 원재료 유형 코드 : 상세설명 참조
        $xml_data['rmaterialTypCd'] = '05';
        # 원산지 코드 : 기타
        $xml_data['orgnTypCd'] = '03';
        # 원산지명
        $xml_data['orgnNmVal'] = '상품 상세설명 참조';
        /*
        switch( $product_data['wonsanji'] )
        {
            # 국산
            case '0' :
                $xml_data['orgnTypCd'] = '03';
                $xml_data['orgnNmVal'] = '국산';
            break;
            # 해외
            case '1' :
                if( $product['wonsanji_text'] != '' )
                {
                    $xml_data['orgnTypCd'] = '03';
                    $xml_data['orgnNmVal'] = $product_data['wonsanji_text'];
                }
            break;
            # 모름 : 입력하지 않습니다
            case '2' :
                $xml_data['orgnTypCd'] = '상품 상세설명 참조';
            break;
        }
        */
        # 축산물 이력번호 : 이력번호 표시대상 아님
        $xml_data['beefTraceStat'] = '02';
        # 판매자 상품코드(자사 상품번호)
        $xml_data['sellerPrdCd'] = $product_data['number'];
        # 부가세/면세상품코드 : 과세상품
        $xml_data['suplDtyfrPrdClfCd'] = '01';
        # 해외구매대행상품 여부 : 일반판매상품
        $xml_data['forAbrdBuyClf'] = '01';
        # 상품상태 : 01(새상품), 02(중고상품:판매방식이05인경우만)
        if ($xml_data['selMthdCd'] == '05') $xml_data['prdStatCd'] = '02';
        else $xml_data['prdStatCd'] = '01';
        # 사용개월수 : 판매방식이 05(중고판매)인 경우 필수 입력, 0은 입력 불가
        if ($xml_data['selMthdCd'] == '05') {
            $xml_data['useMon'] = (int)$product_data['product_use_time_month'];
            if ($xml_data['useMon'] == 0) $xml_data['useMon'] = '99999';
        }
        # 구입당시 판매가 : 중고판매 필수입력, 개발예정
        $xml_data['paidSelPrc'] = '10';
        # 외관/기능상 특이사항 : 중고판매 필수입력, CDATA
        $xml_data['exteriorSpecialNote'] = '<![CDATA[상품 상세설명 참조]]>';
        # 미성년자 구매가능 : 필수
        $xml_data['minorSelCnYn'] = 'Y';

        # 이미지 준비
        $image_list = $this->get_image_list();
        if (count($image_list) == 0) return false;
        # 대표 이미지 URL : 11번가 서버가 다운로드하여 300x300으로 리사이징합니다. url 호출시 Content-Type 정의 필요.
        $xml_data['prdImage01'] = $image_list[0];
        if ($image_list[1]) $xml_data['prdImage02'] = $image_list[1];
        if ($image_list[2]) $xml_data['prdImage03'] = $image_list[2];
        if ($image_list[3]) $xml_data['prdImage04'] = $image_list[3];
        # 목록이미지
        $xml_data['prdImage05'] = $image_list[0];
        # 카드뷰이미지 : 사이즈 이슈로 사용안함 720x360
        # $xml_data['prdImage09'] = $image_list[0];
        # 상세설명
        $xml_data['htmlDetail'] = $this->make_11st_detail();

        $xml_data['ProductCertGroup'] = array();
        $xml_data['ProductCertGroup'][] = array(
            'crtfGrpTypCd' => '01',
            'crtfGrpObjClfCd' => '03',
            'crtfGrpExptTypCd' => '',
        );
        $xml_data['ProductCertGroup'][] = array(
            'crtfGrpTypCd' => '02',
            'crtfGrpObjClfCd' => '03',
            'crtfGrpExptTypCd' => '',
        );
        $xml_data['ProductCertGroup'][] = array(
            'crtfGrpTypCd' => '03',
            'crtfGrpObjClfCd' => '03',
            'crtfGrpExptTypCd' => '',
        );
        $xml_data['ProductCertGroup'][] = array(
            'crtfGrpTypCd' => '04',
            'crtfGrpObjClfCd' => '05',
            'crtfGrpExptTypCd' => '',
        );

        # 상품리뷰/후기 전시여부
        $xml_data['reviewDispYn'] = 'Y';
        $xml_data['reviewOptDispYn'] = 'Y';


        $xml_data['selTermUseYn'] = 'N';
        /*
        # 상품판매기간
        3:101 : 3일
        5:102 : 5일
        7:103 : 7일
        15:104 : 15일
        30:105 : 30일(1개월)
        60:106 : 60일(2개월)
        90:107 : 90일(3개월)
        120:108 : 120일(4개월)
        */
        /*
        $end_time = strtotime($product_data['end_date']);
        $time_diff = $end_time - time();
        if( $time_diff > (86400*120) ) $xml_data['selPrdClfCd'] = '120:108';
        elseif( $time_diff > (86400*90) ) $xml_data['selPrdClfCd'] = '90:107';
        elseif( $time_diff > (86400*60) ) $xml_data['selPrdClfCd'] = '60:106';
        elseif( $time_diff > (86400*30) ) $xml_data['selPrdClfCd'] = '30:105';
        elseif( $time_diff > (86400*15) ) $xml_data['selPrdClfCd'] = '15:104';
        elseif( $time_diff > (86400*7) ) $xml_data['selPrdClfCd'] = '7:103';
        elseif( $time_diff > (86400*5) ) $xml_data['selPrdClfCd'] = '5:102';
        else $xml_data['selPrdClfCd'] = '3:101';
        */

        # 골프딜 제휴할인
        $adjusted_price = 0;
        if ($GLOBALS['version']['auction_product_price'] && $product_data['hm_extra16'] === '골프딜') {
            $sql = "
				SELECT		adjusted_price
				FROM		auction_product_price
				WHERE		product_number = {$product_data['number']}
				AND 		target = '골프딜제휴할인'
				AND 		standard_price = {$product_data['baro_price']}
			";
            $row = mysql_fetch_row(query($sql));
            if ($row !== false) $adjusted_price = $row[0];
        }

        # 판매가
        if ($adjusted_price) $xml_data['selPrc'] = $adjusted_price;
        else $xml_data['selPrc'] = $product_data['baro_price'];

        # 재고수량
        $xml_data['prdSelQty'] = $product_data['jaego'];

        # 최대구매수량
        if (in_array($product_data['number'], array('795006'))) {
            # 최대구매수량 코드 : 01(1회제한)
            $xml_data['selLimitTypCd'] = '01';
            # 최대구매수량 개수
            $xml_data['selLimitQty'] = '12';
            # 재구매기간
            $xml_data['townSelLmtDy'] = '1';
        }

        # 배송방법 : 01(택배)
        $xml_data['dlvWyCd'] = '01';
        /*
        # 배송비 종류
        01 : 무료
        02 : 고정 배송비
        03 : 상품 조건부 무료
        04 : 수량별 차등
        05 : 1개당 배송비
        07 : 판매자 조건부 배송비 2010.08.20 06->07 로 변경
        08 : 출고지 조건부 배송비 2010.10.08 추가
        */
        switch ($product_data['baesong_type']) {
            # 무료
            case '0' :
                $xml_data['dlvCstInstBasiCd'] = '01';
                break;
            # 선결제불가
            # 착불일 때 반품배송비로 기재해본다!
            case '1' :
                $xml_data['dlvCstInstBasiCd'] = '02';
                $xml_data['dlvCst1'] = '10';
                $xml_data['dlvCstInfoCd'] = '01'; # 배송비 상품상세참고
                $xml_data['dlvCstPayTypCd'] = '02'; # 선결제불가
                break;
            # 선결제가능
            case '2' :
                $xml_data['dlvCstInstBasiCd'] = '02';
                $xml_data['dlvCst1'] = (int)$product_data['baesongbi'];
                $xml_data['dlvCstPayTypCd'] = '01'; # 선결제가능
                break;
            # 조건부
            case '3' :
                $xml_data['dlvCstInstBasiCd'] = '03';
                $xml_data['dlvCst1'] = (int)$product_data['baesongbi'];
                $xml_data['PrdFrDlvBasiAmt'] = (int)$product_data['baesong_cut_free'];
                break;
        }
        $xml_data['bndlDlvCnYn'] = 'N'; # 묶음배송 불가
        # 도서산간 추가배송비
        $xml_data['jejuDlvCst'] = $product_data['baesongbi_sangan'];
        $xml_data['islandDlvCst'] = $product_data['baesongbi_sangan'];
        # 출고지 주소 코드
        $xml_data['addrSeqOut'] = $product_data['out_address_value'];
        # 반품배송지 주소 코드
        $xml_data['addrSeqIn'] = $product_data['return_address_value'];
        # 반품배송비
        $xml_data['rtngdDlvCst'] = $product_data['baesongbi_return'];
        # 교환배송비 : 왕복비용이므로, 반품배송비+최초배송비
        $xml_data['exchDlvCst'] = $product_data['baesongbi_return'] + $product_data['baesongbi'];
        # 초기배송비 무료시 부과방법 : 01(편도x2)
        $xml_data['rtngdDlvCd'] = '01';
        # A/S안내
        if ($product_data['as_can'] == '1') {
            $as_can_text = $product_data['as_can_text'] ? $product_data['as_can_text'] : 'A/S가능';
            $xml_data['asDetail'] = "<![CDATA[{$as_can_text}]]>";
        } else {
            $xml_data['asDetail'] = '<![CDATA[A/S불가]]>';
        }
        # 반품/교환 안내
        $xml_data['rtngExchDetail'] = '<![CDATA[상품 상세설명 참조]]>';
        # 상품정보제공고시
        $xml_data['ProductNotification'] = $this->make_11st_ProductNotification();
        # 모델명
        $model_name_search = array('#', '`');
        $model_name_replace = array('No.', "'");
        $model_name_text = str_replace($model_name_search, $model_name_replace, $product_data['model_name_text']);
        if ($product_data['model_name_text']) $xml_data['modelNm'] = htmlspecialchars($model_name_text);
        # 가격비교사이트 등록 여부
        $xml_data['prcCmpExpYn'] = 'Y';

        return "<Product>" . $this->array_to_xml($xml_data) . "</Product>";
    }

    /**
     * 11번가 상품데이터 조회
     */
    public function prodmarket()
    {
        $product = $this->get_product_data();
        $method = 'GET';
        $prdNo = $product['coop_number'];
        if (in_array($prdNo, array('', '999'))) {
            return false;
        }
        $url = "http://api.11st.co.kr/rest/prodmarketservice/prodmarket/{$prdNo}";

        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        if (is_int(strpos($response_array['message'], '상품 정보가 정상적으로 조회'))) {
            return $response_array;
        } else {
            if (!is_int(strpos($response, '<!DOCTYPE HTML'))) {
                $DA = array();
                $DA['reg_date'] = date('Y-m-d H:i:s');
                $DA['description'] = '[11번가] 상품데이터 조회 오류 : ' . $product['number'];
                $DA['source'] = print_r($response, true);
                util::insert_array('developer_alert', $DA);
            }
            return false;
        }
    }

    /**
     * 11번가 상품데이터 조회
     */
    static function prodmarket_manual($prdNo)
    {
        $url = "http://api.11st.co.kr/rest/prodmarketservice/prodmarket/{$prdNo}";
        $method = 'GET';
        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);
        return $response_array;
    }

    /**
     * 11번가 카테고리 번호를 반환합니다
     */
    public function make_11st_category()
    {
        /*
        골프	1001392
            골프클럽	1002434
                드라이버	1008148
                아이언	1008149
                연습용클럽	1008150
                왼손전용클럽	1008151
                    드라이버	1013087
                    아이언	1013088
                    우드	1013089
                    웨지	1013090
                    퍼터	1013091
                    풀세트	1013092
                웨지	1008152
                유틸리티우드	1008153
                주니어클럽	1008154
                중고클럽	1008155
                    드라이버	1013093
                    아이언	1013094
                    우드	1013095
                    웨지	1013096
                    퍼터	1013097
                치퍼	1008156
                퍼터	1008157
                페어웨이우드	1008158
                골프 풀세트	1013423
            골프피팅용품	1002435
                그립	1008159
                샤프트	1008160
                헤드	1008161
            골프필드용품	1002436
                거리측정기	1008162
                골프우산	1008163
                골프투어	1008164
                골프티	1008165
                    나무티	1013098
                    자석티	1013099
                    플라스틱티	1013100
                망원형 측정기	1008166
                볼라이너	1008167
                볼마커	1008168
                용품 기타	1008169
                헤드커버	1008170
                    드라이버	1013101
                    아이언	1013102
                    우드	1013103
                    퍼터	1013104
            골프연습용품	1002437
                골프책	1008136
                스윙연습	1008137
                실외연습	1008138
                연습용품	1008139
                연습용품 기타	1008140
                자세 교정	1008141
                퍼팅연습	1008142
            골프화	1002438
                남성용	1008171
                여성용	1008172
            골프공	1002439
                2피스	1008125
                3피스	1008126
                4피스	1008127
                로스트볼	1008128
            골프모자	1002440
                골프모자	1008129
            골프양말	1002441
                골프양말	1008135
            골프잡화	1002442
                기타	1008143
                밴드	1008144
                벨트	1008145
            골프장갑	1002443
                남성용	1008146
                여성용	1008147
            골프백	1002444
                백세트	1008130
                보스턴백	1008131
                캐디백	1008132
                하프백	1008133
                항공커버	1008134
            남성용 골프의류	1002445
                골프의류 기타	1008173
                골프의류 풀세트	1008174
                긴팔티셔츠	1008175
                니트	1008176
                바지	1008177
                반팔티셔츠	1008178
                비옷	1008179
                자켓	1008180
                조끼	1008181
            여성용 골프의류	1002446
                8-10부바지	1008182
                골프의류 기타	1008183
                골프의류 풀세트	1008184
                긴팔티셔츠	1008185
                니트	1008186
                반바지	1008187
                반팔티셔츠	1008188
                비옷	1008189
                스커트	1008190
                자켓	1008191
                조끼	1008192
        */
        $product_data = $this->get_product_data();
        $category = explode('r', $product_data['category']);

        /*
         * 클럽 분류 : 주니어, 왼손, 새상품, 중고상품 순서로 분류합니다
         */
        if (in_array($category[0], array('1', '2032', '2033', '2034', '2035', '2036', '2037'))) {
            # 클럽 주니어
            if ($category[1] == '05') {
                return '1008154';
            } # 클럽 왼손
            elseif ($category[1] == '04') {
                switch ($category[0]) {
                    # 드라이버
                    case '1' :
                        return '1013087';
                    # 아이언
                    case '2032' :
                        return '1013088';
                    # 풀세트
                    case '2033' :
                        return '1013092';
                    # 페어웨이우드
                    case '2034' :
                        return '1013089';
                    # 유틸리티(우드)
                    case '2035' :
                        return '1013089';
                    # 웨지/치퍼
                    case '2036' :
                        return '1013090';
                    # 퍼터
                    case '2037' :
                        return '1013091';
                }
            } # 클럽 새상품
            elseif ($product_data['product_sangtae'] == '0') {
                switch ($category[0]) {
                    # 드라이버
                    case '1' :
                        return '1008148';
                    # 아이언
                    case '2032' :
                        return '1008149';
                    # 풀세트
                    case '2033' :
                        return '1013423';
                    # 페어웨이우드
                    case '2034' :
                        return '1008158';
                    # 유틸리티(우드)
                    case '2035' :
                        return '1008153';
                    # 웨지/치퍼
                    case '2036' :
                        # 상품명에 웨지가 있으면 웨지로 우선 분류
                        if (
                            is_numeric(strpos($product_data['product_name'], '웨지')) ||
                            is_numeric(stripos($product_data['product_name'], 'wedge'))
                        ) {
                            return '1008152';
                        } # 웨지가 없고 치퍼가 있으면 치퍼로 분류
                        elseif
                        (
                            is_numeric(strpos($product_data['product_name'], '치퍼')) ||
                            is_numeric(stripos($product_data['product_name'], 'chipper'))
                        ) {
                            return '1008156';
                        } # 둘 다 없으면 웨지로 분류
                        else {
                            return '1008152';
                        }
                    # 퍼터
                    case '2037' :
                        return '1008157';
                }
            } # 클럽 중고상품
            elseif ($product_data['product_sangtae'] == '1') {
                switch ($category[0]) {
                    # 드라이버
                    case '1' :
                        return '1013093';
                    # 아이언
                    case '2032' :
                        return '1013094';
                    # 풀세트(새상품과 동일)
                    case '2033' :
                        return '1013423';
                    # 페어웨이우드
                    case '2034' :
                        return '1013095';
                    # 유틸리티(우드)
                    case '2035' :
                        return '1013095';
                    # 웨지/치퍼(웨지로 통합)
                    case '2036' :
                        return '1013096';
                    # 퍼터
                    case '2037' :
                        return '1013097';
                }
            }
        }
        /*
         * 클럽 외 상품 분류
         */
        # 클럽기타
        elseif ($category[0] == '2152') {
            # 그립
            if ($category[1] == '03') {
                return '1008159';
            }
            # 낱개아이언
            if ($category[1] == '04') {
                # 중고 아이언으로 분류
                return '1013094';
            }
        } # 샤프트
        elseif ($category[0] == '3352') {
            return '1008160';
        } # 헤드
        elseif ($category[0] == '3369') {
            return '1008161';
        } # 골프용품
        elseif ($category[0] == '2153') {
            switch ($category[1]) {
                # 골프공
                case '01' :
                    switch ($category[2]) {
                        # 로스트볼
                        case '05' :
                            return '1008128';
                        # 2피스
                        case '01' :
                            return '1008125';
                        # 3피스
                        case '02' :
                            return '1008126';
                        # 4피스
                        case '03' :
                            return '1008127';
                        # 5피스
                        case '06' :
                            return '1008127';
                    }
                    break;
                # 골프백
                case '02' :
                    switch ($category[2]) {
                        # 골프백세트
                        case '01' :
                            return '1008130';
                        # 골프백(캐디백)
                        case '02' :
                            return '1008132';
                        # 백팩(골프잡화/기타)
                        case '08' :
                            return '1008143';
                        # 여행가방(골프잡화/기타)
                        case '09' :
                            return '1008143';
                        # 보스턴백
                        case '03' :
                            return '1008131';
                        # 하프백
                        case '04' :
                            return '1008133';
                        # 스택드백(골프잡화/기타)
                        case '05' :
                            return '1008143';
                        # 항공커버
                        case '06' :
                            return '1008134';
                        # 파우치백(골프잡화/기타)
                        case '07' :
                            return '1008143';
                    }
                    break;
                # 골프화
                case '03' :
                    switch ($category[2]) {
                        # 남성용
                        case '01' :
                            return '1008171';
                        # 여성용
                        case '02' :
                            return '1008172';
                        # 연습화
                        case '03' :
                            return '1008171';
                    }
                    break;
                # 골프장갑
                case '04' :
                    # 여성용
                    if (is_numeric(strpos($product_data['product_name'], '여성'))) {
                        return '1008147';
                    } # 남성용
                    else {
                        return '1008146';
                    }
                    break;
                # 필드용품
                case '05' :
                    switch ($category[2]) {
                        # 거리측정기
                        case '01' :
                            return '1008162';
                        # 골프티
                        case '02' :
                            if (is_numeric(strpos($product_data['product_name'], '플라스틱'))) {
                                return '1013100';
                            } elseif (is_numeric(strpos($product_data['product_name'], '나무'))) {
                                return '1013098';
                            } elseif (is_numeric(strpos($product_data['product_name'], '자석'))) {
                                return '1013099';
                            } # 해당없으면 플라스틱으로 등록
                            else {
                                return '1013100';
                            }
                        # 볼마커/라이너
                        case '03' :
                            if (is_numeric(strpos($product_data['product_name'], '마커'))) {
                                return '1008168';
                            } elseif (is_numeric(strpos($product_data['product_name'], '라이너'))) {
                                return '1008167';
                            } # 해당없으면 볼마커로 등록
                            else {
                                return '1008168';
                            }
                        # 골프우산
                        case '04' :
                            return '1008163';
                        # 기타필드용품
                        case '05' :
                            return '1008169';
                    }
                    break;
                # 연습용품
                case '06' :
                    switch ($category[2]) {
                        # 퍼팅연습용품
                        case '01' :
                            return '1008142';
                        # 퍼팅매트
                        case '04' :
                            return '1008142';
                        # 스윙연습용품
                        case '03' :
                            return '1008137';
                        # 기타연습용품
                        case '02' :
                            return '1008140';
                    }
                    break;
                # 골프모자
                case '07' :
                    return '1008129';
                    break;
                # 벨트/양말/썬글라스
                case '08' :
                    if (is_numeric(strpos($product_data['product_name'], '양말'))) return '1008135';
                    if (is_numeric(strpos($product_data['product_name'], '벨트'))) return '1008145';
                    return '1008143';
                    break;
                # 헤드커버
                case '09' :
                    if (is_numeric(strpos($product_data['product_name'], '드라이버'))) return '1013101';
                    if (is_numeric(strpos($product_data['product_name'], '아이언'))) return '1013102';
                    if (is_numeric(strpos($product_data['product_name'], '우드'))) return '1013103';
                    if (is_numeric(strpos($product_data['product_name'], '퍼터'))) return '1013104';
                    # 해당 없으면 드라이버커버로 등록
                    return '1013101';
                    break;
                # 기능성용품
                case '10' :
                    return '1008169';
                    break;
                # 기타골프용품
                case '11' :
                    return '1008169';
                    break;
            }
        } # 골프의류
        elseif ($category[0] == '2154') {
            # 남성용
            if ($category[1] == '01') {
                switch ($category[2]) {
                    # 자켓
                    case '01' :
                        return '1008180';
                    # 티셔츠
                    case '03' :
                        if (is_numeric(strpos($product_data['product_name'], '반팔'))) return '1008178';
                        if (is_numeric(strpos($product_data['product_name'], '긴팔'))) return '1008175';
                        # 해당 없으면 반팔로 등록
                        return '1008178';
                    # 니트
                    case '02' :
                        return '1008176';
                    # 조끼
                    case '04' :
                        return '1008181';
                    # 바람막이
                    case '06' :
                        return '1008180';
                    # 바지
                    case '05' :
                        return '1008177';
                    # 비옷
                    case '07' :
                        return '1008179';
                    # 기능성이너웨어
                    case '08' :
                        return '1008173';
                    # 남성의류기타
                    case '09' :
                        return '1008173';
                }
            }
            # 여성용
            if ($category[1] == '02') {
                switch ($category[2]) {
                    # 자켓
                    case '01' :
                        return '1008191';
                    # 티셔츠
                    case '02' :
                        if (is_numeric(strpos($product_data['product_name'], '반팔'))) return '1008188';
                        if (is_numeric(strpos($product_data['product_name'], '긴팔'))) return '1008185';
                        # 해당 없으면 반팔로 등록
                        return '1008188';
                    # 니트
                    case '03' :
                        return '1008186';
                    # 조끼
                    case '04' :
                        return '1008192';
                    # 바람막이
                    case '05' :
                        return '1008191';
                    # 바지
                    case '06' :
                        if (is_numeric(strpos($product_data['product_name'], '반바지'))) return '1008187';
                        # 반바지가 아니면 8-10부 바지
                        return '1008185';
                    # 스커트
                    case '07' :
                        return '1008190';
                    # 비옷
                    case '08' :
                        return '1008189';
                    # 기능성이너웨어
                    case '09' :
                        return '1008183';
                    # 여성의류기타
                    case '10' :
                        return '1008183';
                }
            }
        } # 스크린장비
        elseif ($category[0] == '2261') {
            # 연습용품 기타
            return '1008140';
        }
    }

    /**
     * HS코드
     */
    public function make_hs_code()
    {
        /*
        9506310000	골프채(완제품의 것에 한한다)
        9506391000	골프채의 부분품
        4203212000	골프장갑
        9506320000	골프공
        95063	골프채와 기타의 골프용품
        */
        $product_data = $this->get_product_data();
        $category = explode('r', $product_data['category']);

        # 골프채 완제품
        if (in_array($category[0], array('1', '2032', '2033', '2034', '2035', '2036', '2037'))) return '9506310000';
        # 골프채 부분품(헤드,샤프트,그립)
        if (
            in_array($category[0], array('3352', '3369')) ||
            ($category[0] == '2152' && $category[1] == '03')
        ) return '9506391000';
        # 골프장갑
        if ($category[0] == '2153' && $category[1] == '04') return '4203212000';
        # 골프공
        if ($category[0] == '2153' && $category[1] == '01') return '9506320000';
        # 기타
        return '95063';
    }

    /**
     * 상품명
     * 50Byte, 한글25자, 영문/숫자 50자 이내
     * <![CDATA[상품명]]>
     */
    public function make_11st_product_name()
    {
        $product_data = $this->get_product_data();
        $product_name = $product_data['product_name'];
        if (marketData::isProductFilteringSeller($product_data['id']))
            $product_name = filtering_telno($product_name);
        # 태그제거
        $product_name = util::removeTags($product_name, 'a|script');
        # 길이 조절
        $product_name = util::str_ellipsis($product_name, 45);
        return "<![CDATA[{$product_name}]]>";
    }

    /**
     * 상품이미지 주소 배열
     */
    public function get_image_list()
    {
        $product_data = $this->get_product_data();
        $image_list = array();
        for ($i = 1; $i < 11; ++$i) {
            $each = $product_data["img{$i}"];
            if ($each == '') continue;
            if ($each[0] != '/') $each = "/{$each}";

            $web_url = "{$GLOBALS['img_url']}{$each}";
            $server_path = str_replace($GLOBALS['main_url'], $_SERVER['DOCUMENT_ROOT'], $web_url);

            # 이미지 사이즈 확인
            $image_size = getImageSize($server_path);
            $width_lt_300 = $image_size[0] < 300;
            $height_lt_300 = $image_size[1] < 300;
            # 300x300 미만일 경우 확대 처리
            if ($width_lt_300 || $height_lt_300) {
                $thumb_option = array();
                $thumb_option['file'] = $server_path;
                # 종횡비
                $aspect_ratio = $image_size[0] / $image_size[1];
                # 더 작은 쪽을 300으로 맞춘다
                if ($image_size[0] < $image_size[1]) {
                    $thumb_option['width'] = 300;
                    $thumb_option['height'] = round($thumb_option['width'] * 1 / $aspect_ratio);
                } else {
                    $thumb_option['height'] = 300;
                    $thumb_option['width'] = round($thumb_option['height'] * $aspect_ratio);
                }
                $thumb = $GLOBALS['main_url'] . es_extraction::getThumbnail($thumb_option);
                $image_list[] = $thumb;
            } else {
                $image_list[] = $web_url;
            }
        }
        return $image_list;
    }

    /**
     * 상품 상세설명
     * 공지,이미지,상품기본정보,상품상세설명,반품안내
     */
    public function make_11st_detail()
    {
        $product_data = $this->get_product_data();
        if ($product_data['number'] === '1090514')
            return $this->make_11st_detail_1090514();

        $html = array();
        $style['box_wrapper'] = 'padding:20px 50px; border:2px solid #999; margin-bottom:50px; text-align:left; font-size:1.4em;';
        $style['box_title'] = 'text-align:center; font-size:1.6em; margin-bottom:20px; font-weight:bold; color:#666;';
        $style['row_wrapper'] = 'margin-bottom:20px;';
        $style['row_title'] = 'display:inline; color:#666;';
        $style['row_content'] = 'display:inline; font-weight:bold;';
        $style['image_wrapper'] = 'margin-bottom:50px;';
        $style['image_row'] = 'margin-bottom:10px;';
        $style['image'] = 'display:block; max-width:100%; margin:0 auto;';
        $style['return_info_row'] = 'margin-bottom:20px;';

        $html['directBuyBanner'] = "
			<a href='http://www.11st.co.kr/product/SellerProductDetail.tmall?method=getSellerProductDetail&prdNo=2058177270&snscode=qr' target='_blank' style='display:block; max-width:100%;'>
				<img src='http://pricegolf.co.kr/img/directBuyBanner.gif' alt='직매입 서비스' style='display:block; max-width:100%;'>
			</a>
		";

        $html['title'] = "<div style='{$style['box_title']} margin-top:50px;'>{$product_data['product_name']}</div>";

        # 공지
        $html['notice'] = '';
        # 공지사항
        $holiday_start = strtotime('2019-01-28 00:00:00');
        $holiday_end = strtotime('2019-02-06 23:59:59');
        $TIME = time();
        if ($holiday_start < $TIME && $TIME < $holiday_end) {
            $notice_text = "※ 설연휴 배송안내 > <span style='color:#c00'>2019/01/30(수) 16시</span>이후 주문건들은 <span style='color:#c00'>2019/02/07(목)</span>부터 순차적으로 발송됩니다.";
        }

        if ($product_data['id'] == '2winlose1' && $TIME < 1550545199) {
            $notice_text = "※ 해외출장으로 인해 <span style='color:#c00'>2019/02/13(수)</span>이후 주문건들은 <span style='color:#c00'>2019/02/19(화)</span>이후 순차적으로 발송됩니다.";
        }

        include("{$_SERVER['DOCUMENT_ROOT']}/class/Notice.php");
        $notice_inst = new Notice();
        $notice_text = $notice_inst->getNoticeHtml($product_data['id']);

        if ($notice_text) {
            $html['notice'] = "<div style='{$style['box_wrapper']}'>";
            $html['notice'] .= "<div style='font-size:1em; font-weight:bold;'>{$notice_text}</div>";
            $html['notice'] .= "</div>";
        }

        # 이미지
        $image_list = $this->get_image_list();
        $html['image'] = "<div style='{$style['image_wrapper']}'>";
        for ($i = 0, $end = count($image_list); $i < $end; ++$i) {
            $html['image'] .= "<div style='{$style['image_row']}'><img src='{$image_list[$i]}' style='{$style['image']}' alt='상품사진'></div>";
        }
        $html['image'] .= '</div>';

        # 상품기본정보
        $html['default_info'] = "<div style='{$style['box_wrapper']}'>";
        # 상품기본정보 : 제목
        $html['default_info'] .= "<div style='{$style['box_title']}'>[ 상품 기본 정보 ]</div>";
        # 상품기본정보 : 상품명
        $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
        $html['default_info'] .= "<span style='{$style['row_title']}'>[ 상품명 ] : </span>";
        $html['default_info'] .= "<span style='{$style['row_content']}'>{$product_data['product_name']}<span style='color:#999; font-size:0.8em'>(상품코드:{$product_data['product_code']})</span></span>";
        $html['default_info'] .= "</div>";
        # 상품기본정보 : 상품분류
        $category_text = implode(' &gt; ', marketData::getInstance()->getCategoryTitle($product_data['category']));
        $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
        $html['default_info'] .= "<span style='{$style['row_title']}'>[ 상품분류 ] : </span>";
        $html['default_info'] .= "<span style='{$style['row_content']}'>{$category_text}</span>";
        $html['default_info'] .= "</div>";
        # 상품기본정보 : 브랜드
        if ($product_data['brand_name'] != '기타 BRAND') {
            $brandName = htmlspecialchars($product_data['brand_name']);
            $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
            $html['default_info'] .= "<span style='{$style['row_title']}'>[ 브랜드 ] : </span>";
            $html['default_info'] .= "<span style='{$style['row_content']}'>{$brandName}</span>";
            $html['default_info'] .= "</div>";
        }
        # 상품기본정보 : 모델명
        if ($product_data['model_name_text'] != '') {
            $modelNameText = htmlspecialchars($product_data['model_name_text']);
            $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
            $html['default_info'] .= "<span style='{$style['row_title']}'>[ 모델명 ] : </span>";
            $html['default_info'] .= "<span style='{$style['row_content']}'>{$modelNameText}</span>";
            $html['default_info'] .= "</div>";
        }
        # 상품기본정보 : 제품상태
        if ($product_data['product_use_time'] == '0') $use_time_text = '미사용 / 새상품';
        else $use_time_text = "(중고) {$product_data['product_use_time']}% / 사용기간 : {$product_data['product_use_time_month']}개월";
        $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
        $html['default_info'] .= "<span style='{$style['row_title']}'>[ 제품상태 ] : </span>";
        $html['default_info'] .= "<span style='{$style['row_content']}'>{$use_time_text}</span>";
        $html['default_info'] .= "</div>";
        # 상품기본정보 : 정품여부
        switch ($product_data['derive']) {
            case '1' :
                $derive_text = '아시안스펙 정품';
                break;
            case '2' :
                $derive_text = '아시안스펙 직수입(병행수입)';
                break;
            case '3' :
                $derive_text = '미국스펙 정품';
                break;
            case '4' :
                $derive_text = '미국스펙 직수입(병행수입)';
                break;
            case '5' :
                $derive_text = '피팅클럽';
                break;
            case '6' :
                $derive_text = '정확히 모름';
                break;
            default :
                $derive_text = '정확히 모름';
                break;
        }
        $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
        $html['default_info'] .= "<span style='{$style['row_title']}'>[ 정품여부 ] : </span>";
        $html['default_info'] .= "<span style='{$style['row_content']}'>{$derive_text}</span>";
        if ($product_data['id'] != 'pricegolf' && $product_data['derive'] != '6') {
            $html['default_info'] .= "<div style='color:#999; font-size:0.9em;'>(정품여부는 판매자로부터 서약을 받은 내용입니다. 표시내용과 다를시 반품/환불가능)</div>";
        }
        $html['default_info'] .= "</div>";
        # 상품기본정보 : 확장정보
        for ($i = 1; $i < 6; ++$i) {
            $str_i = sprintf('%02d', $i);
            $row_title = $product_data["ext{$str_i}t"];
            $row_content = $product_data["ext{$str_i}"];
            if ($row_title == '' || $row_content == '') break;
            $html['default_info'] .= "<div style='{$style['row_wrapper']}'>";
            $html['default_info'] .= "<span style='{$style['row_title']}'>[ {$row_title} ] : </span>";
            $html['default_info'] .= "<span style='{$style['row_content']}'>{$row_content}</span>";
            $html['default_info'] .= "</div>";
        }
        # 상품기본정보 : 중개안내
        if ($product_data['id'] != 'pricegolf') {
            $html['default_info'] .= "<div style='color:#00c; font-weight:bold;'>";
            $html['default_info'] .= "이 상품은 프라이스골프를 통해 일반 판매자가 등록한 상품입니다.<br>";
            $html['default_info'] .= "상품 문의는 [온라인]으로만 가능합니다.<br>";
            $html['default_info'] .= "이 상품은 묶음배송이 되지 않습니다.</div>";
        }
        # 상품기본정보 끝
        $html['default_info'] .= "</div>";


        # 상품상세설명 준비
        $detail_text = $product_data['comment'];

        if (marketData::isProductFilteringSeller($product_data['id'])) {
            $inst = new string_filter();
            $detail_text = $inst->filtering_telno($detail_text);
        }
        # 이미지 크기 제한 : 11번가 860px;
        preg_match_all('/<img[^>]*>/i', $detail_text, $img_tag_list);
        $img_tag_list = array_values(array_unique($img_tag_list[0], SORT_STRING));
        for ($i = 0, $end = count($img_tag_list); $i < $end; ++$i) {
            $tm_instance = new tag_manager($img_tag_list[$i]);
            $tm_instance->css('height', null);
            $tm_instance->css('max-width', '840px');
            $replace_list[] = $tm_instance->get_html();
        }
        $detail_text = str_replace($img_tag_list, $replace_list, $detail_text);
        # BR태그를 PHP_EOL로 변경
        $detail_text = str_ireplace(array('<br>', '<br />'), PHP_EOL, $detail_text);

        # 논현점상품 웹절대경로를 웹전체경로로 변경
        if ($product_data['id'] == 'pricegolf') {
            $detail_text = str_replace("src=\"/", "src=\"{$GLOBALS['main_url']}/", $detail_text);
        }

        # 각 라인을 div태그로 묶습니다
        $detail_text_explode = explode(PHP_EOL, $detail_text);
        for ($i = 0, $end = count($detail_text_explode); $i < $end; ++$i) {
            $row_text = trim($detail_text_explode[$i]);
            $detail_text_explode[$i] = "<div style='margin-bottom:5px;'>{$row_text}</div>";
        }
        $detail_text = implode('', $detail_text_explode);
        # 상품상세설명 시작
        $html['detail_text'] = "<div style='{$style['box_wrapper']}'>";
        # 상품상세설명 : 제목
        $html['detail_text'] .= "<div style='{$style['box_title']}'>[ 상품 상세 설명 ]</div>";
        # 상품상세설명 : 내용
        $html['detail_text'] .= $detail_text;
        # 상품상세설명 끝
        $html['detail_text'] .= "</div>";

        # 배송안내
        $html['baesong_info'] = "<div style='{$style['box_wrapper']}'>";
        $html['baesong_info'] .= "<div style='{$style['box_title']}'>[ 배송안내 ]</div>";
        $html['baesong_info'] .= "<div style='{$style['return_info_row']}'>1. 발송일은 각 상품의 발송처 사정에 따라 상이할 수 있습니다.</div>";
        if ($product_data['baesong_type'] == '1') {
            $html['baesong_info'] .= "<div style='{$style['return_info_row']}'>2. [택배-수령후 지불]인 상품은 발송처, 택배사 사정에 따라 배송비가 달라질 수 있습니다.</div>";
            $html['baesong_info'] .= "<div style='{$style['return_info_row']}'>&nbsp; (상품 수령시 택배기사가 청구하는 금액을 지불하시게 됩니다.)</div>";
        }
        $html['baesong_info'] .= "</div>";

        # 반품안내 시작
        $html['return_info'] = "<div style='{$style['box_wrapper']}'>";
        $html['return_info'] .= "<div style='{$style['box_title']}'>[ 반품 안내 ]</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']} font-weight:bold;'>[ 반품이 불가한 경우 ]</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>1. 상품 도착 후 5일 경과 (은행 영업일 기준)</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>2. 상품이 최초 배송 상태와 달라진 경우(시타, 비닐포장 제거, 상품 사용 등)</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>3. 주문 확인 후 제작에 들어가는 주문제작상품</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']} font-weight:bold;'>[ 반품 운송비 ]</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>1. 반품시에는 왕복 운송비용을 판/구매자 중 반품의 원인을 제공한 측에서 부담해야 합니다.</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>- 구매자의 단순 변심 : 구매자가 왕복 운송비용 부담</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>- 사진, 설명과 다른 제품을 받았을 경우 : 판매자가 왕복 운송비용 부담(협의우선)</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>2. 판매자에게 뚜렷한 귀책사유가 없는한 반품시 왕복 운송비용은 구매자 부담을 원칙으로 합니다.</div>";
        # 반품안내 끝
        $html['return_info'] .= "</div>";

        $result =
            '<![CDATA['
            . $html['directBuyBanner']
            . $html['title']
            . $html['image']
            . $html['notice']
            . $html['default_info']
            . $html['detail_text']
            . $html['baesong_info']
            . $html['return_info']
            . $html['directBuyBanner']
            . ']]>';
        return $result;
    }

    public function make_11st_detail_1090514()
    {
        $product_data = $this->get_product_data();

        $html = array();
        $style['box_wrapper'] = 'padding:20px 50px; border:2px solid #999; margin-bottom:50px; text-align:left; font-size:1.4em;';
        $style['box_title'] = 'text-align:center; font-size:1.6em; margin-bottom:20px; font-weight:bold; color:#666;';
        $style['row_wrapper'] = 'margin-bottom:20px;';
        $style['row_title'] = 'display:inline; color:#666;';
        $style['row_content'] = 'display:inline; font-weight:bold;';
        $style['image_wrapper'] = 'margin-bottom:50px;';
        $style['image_row'] = 'margin-bottom:10px;';
        $style['image'] = 'display:block; max-width:100%; margin:0 auto;';
        $style['return_info_row'] = 'margin-bottom:20px;';

        $html['directBuyBanner'] = "
			<a href='http://www.11st.co.kr/product/SellerProductDetail.tmall?method=getSellerProductDetail&prdNo=2058177270&snscode=qr' target='_blank' style='display:block; max-width:100%;'>
				<img src='http://pricegolf.co.kr/img/directBuyBanner.gif' alt='직매입 서비스' style='display:block; max-width:100%;'>
			</a>
		";

        if (1533740400 <= $GLOBALS['TIME']) {
            $html['title'] = "<div style='{$style['box_title']} margin-top:50px;'>{$product_data['product_name']}</div>";
        } # 8월 8일 이벤트 처리
        else {
            $html['title'] = "
				<div style='{$style['box_title']} margin-top:50px;'>
					{$product_data['product_name']}
					<br><span style='color:blue;'>(8월 8일 레저Day 단하루! T맴버십 할인 99,000원)</span>
				</div>
			";
            $product_data['comment'] = str_replace(
                'Pro V1 and Pro V1x 4.jpg'
                , 'Pro V1 and Pro V1x 4_180808.jpg'
                , $product_data['comment']
            );
        }

        # 상품상세설명 준비
        $detail_text = $product_data['comment'];
        # 이미지 크기 제한
        preg_match_all('/<img[^>]*>/i', $detail_text, $img_tag_list);
        $img_tag_list = array_values(array_unique($img_tag_list[0], SORT_STRING));
        for ($i = 0, $end = count($img_tag_list); $i < $end; ++$i) {
            $tm_instance = new tag_manager($img_tag_list[$i]);
            $tm_instance->css('height', null);
            $tm_instance->css('max-width', '900px');
            $replace_list[] = $tm_instance->get_html();
        }
        $detail_text = str_replace($img_tag_list, $replace_list, $detail_text);
        # BR태그를 PHP_EOL로 변경
        $detail_text = str_ireplace(array('<br>', '<br />'), PHP_EOL, $detail_text);

        # 논현점상품 웹절대경로를 웹전체경로로 변경
        if ($product_data['id'] == 'pricegolf') {
            $detail_text = str_replace("src=\"/", "src=\"{$GLOBALS['main_url']}/", $detail_text);
        }

        # 각 라인을 div태그로 묶습니다
        $detail_text_explode = explode(PHP_EOL, $detail_text);
        for ($i = 0, $end = count($detail_text_explode); $i < $end; ++$i) {
            $row_text = trim($detail_text_explode[$i]);
            $detail_text_explode[$i] = "<div style='margin-bottom:5px;'>{$row_text}</div>";
        }
        $detail_text = implode('', $detail_text_explode);
        # 상품상세설명 : 내용
        $html['detail_text'] .= $detail_text;
        $html['detail_text'] = "<div style='margin-bottom:50px;'>{$html['detail_text']}</div>";


        # 반품안내 시작
        $html['return_info'] = "<div style='{$style['box_wrapper']}'>";
        $html['return_info'] .= "<div style='{$style['box_title']}'>[ 반품 안내 ]</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']} font-weight:bold;'>[ 반품이 불가한 경우 ]</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>1. 상품 도착 후 5일 경과 (은행 영업일 기준)</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>2. 상품이 최초 배송 상태와 달라진 경우(시타, 비닐포장 제거, 상품 사용 등)</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']} font-weight:bold;'>[ 반품 운송비 ]</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>1. 반품시에는 왕복 운송비용을 판/구매자 중 반품의 원인을 제공한 측에서 부담해야 합니다.</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>- 구매자의 단순 변심 : 구매자가 왕복 운송비용 부담</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>- 사진, 설명과 다른 제품을 받았을 경우 : 판매자가 왕복 운송비용 부담(협의우선)</div>";
        $html['return_info'] .= "<div style='{$style['return_info_row']}'>2. 판매자에게 뚜렷한 귀책사유가 없는한 반품시 왕복 운송비용은 구매자 부담을 원칙으로 합니다.</div>";
        # 반품안내 끝
        $html['return_info'] .= "</div>";

        $result =
            '<![CDATA['
            . $html['title']
            . $html['detail_text']
            . $html['return_info']
            . $html['directBuyBanner']
            . ']]>';
        return $result;
    }

    /**
     * 상품정보제공고시
     */
    public function make_11st_ProductNotification()
    {
        $product_data = $this->get_product_data();
        $xml = '';
        # 의류 891011
        if (is_numeric(strpos($product_data['category'], '2154'))) {
            $xml .= '<type>891011</type>';
            $xml .= '<item><code>11835</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759095</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760437</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760034</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760386</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759308</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11905</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23756520</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759468</code><name>상품상세설명 참조</name></item>';
        } # 신발 : 891012
        elseif (is_numeric(strpos($product_data['category'], '2153r03'))) {
            $xml .= '<type>891012</type>';
            $xml .= '<item><code>11835</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759972</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759095</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760386</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760034</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>40748371</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11905</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760437</code><name>상품상세설명 참조</name></item>';
        } # 가방 : 891013
        elseif (is_numeric(strpos($product_data['category'], '2153r02'))) {
            $xml .= '<type>891013</type>';
            $xml .= '<item><code>11835</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759972</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759095</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11848</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11932</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11908</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11905</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760386</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760437</code><name>상품상세설명 참조</name></item>';
        } # 패션잡화(모자/벨트/액세서리) : 891014
        elseif (
            is_numeric(strpos($product_data['category'], '2153r07')) ||
            is_numeric(strpos($product_data['category'], '2153r08'))
        ) {
            $xml .= '<type>891014</type>';
            $xml .= '<item><code>23759972</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759095</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11848</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760386</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760034</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11908</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11905</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760437</code><name>상품상세설명 참조</name></item>';
        } # 골프용품
        else {
            $xml .= '<type>891035</type>';
            $xml .= '<item><code>11835</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11800</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760223</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759938</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23759095</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>17461</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760437</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11905</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>11900</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23760386</code><name>상품상세설명 참조</name></item>';
            $xml .= '<item><code>23756377</code><name>상품상세설명 참조</name></item>';
        }
        return $xml;
    }
}