<?php
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.php");

class st11_product_list extends st11
{
    /**
     * 11번가에서 상품목록을 조회합니다
     */
    static function prodmarket($start, $end)
    {
        $url = 'http://api.11st.co.kr/rest/prodmarketservice/prodmarket';
        $method = 'POST';

        $xml_data = array();
        $xml_data['start'] = $start;
        $xml_data['end'] = $end;
        $xml_data['limit'] = $end - $start;
        $xml_data['selStatCd'] = '103'; # 판매중 상품만 가져옵니다

        $xml = '<SearchProduct>' . parent::array_to_xml($xml_data) . '</SearchProduct>';

        $response = parent::request($url, $method, $xml);
        $response_array = parent::xml_to_array($response);

        if ($response_array == false) {
            return false;
        }

        if (isset($response_array['product'][0])) {
            $response_array = $response_array['product'];
        } elseif (isset($response_array['product']['prdNo'])) {
            $response_array = array($response_array['product']);
        }
        return $response_array;
    }

    /**
     * 11번가에서 상품목록을 조회하여 DB에 입력합니다
     */
    static function insert_prodmarket()
    {
        # 테이블 비우기
        query('TRUNCATE `_test_st11`');
        for ($i = 0, $end = 10000; $i < $end; ++$i) {
            $list = self::prodmarket($i * 100, ($i + 1) * 100);
            if ($list == false) {
                break;
            }

            $count = count($list);
            echoDev("{$i}페이지 : {$count}개");


            $insert = "INSERT INTO _test_st11 (prdNo, selPrc, selStatCd, selStatNm) VALUES ";
            $insert_rows = array(); # prdNo, selPrc, selStatCd, selStatNm
            for ($j = 0; $j < $count; ++$j) {
                $row = $list[$j];
                $insert_row = array($row['prdNo'], $row['selPrc'], $row['selStatCd'], $row['selStatNm']);
                $insert_row = "('" . implode("','", $insert_row) . "')";
                $insert_rows[] = $insert_row;
            }
            $insert .= implode(',', $insert_rows);


            query($insert);
        }
    }

    static function compare($start = '0', $limit = '500')
    {
        $sql = "
			SELECT		APC.coop_number, AP.number, AP.product_stats, AP.jaego
			FROM		(제휴상품연동정보테이블) AS APC
			LEFT OUTER JOIN (상품테이블) AS AP ON APC.product_number = AP.number
			WHERE		APC.coop_name = '11st'
			ORDER BY	APC.number
			LIMIT		{$start}, {$limit}
		";
        $list = util::query_to_array($sql);
        for ($i = 0, $end = count($list); $i < $end; ++$i) {
            $product = $list[$i];
            if ($product['product_stats'] == '0' && $product['jaego'] > 0) $product['product_stats_text'] = '판매중';
            else $product['product_stats_text'] = '판매종료';
            $st11 = self::prodmarket($list[$i]['coop_number']);
            $TS = array();
            $TS['product_number'] = $product['number'];
            $TS['product_stats'] = $product['product_stats_text'];
            $TS['coop_number'] = $st11['prdNo'];
            if ($st11 == false) $TS['coop_stats'] = '상품없음';
            else $TS['coop_stats'] = $st11['selStatNm'];
            util::insert_array('_test_st11', $TS);
        }
    }

    static function compare_sync()
    {
        $sql = "
			SELECT		*
			FROM		_test_st11 AS A
			WHERE
			(
				( A.product_stats = '판매중' && A.coop_stats <> '판매중' )
				OR			( A.product_stats <> '판매중' && A.coop_stats = '판매중' )
			)
			AND		A.coop_stats <> '상품없음'
			LIMIT	3
		";
        $list = util::query_to_array($sql);

        include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.product.php");

        for ($i = 0, $end = count($list); $i < $end; ++$i) {

            $inst = new st11_product($list[$i]['product_number']);
            $result = $inst->edit_product();
            echoDev($list[$i], $result);
            if ($result) {
                $sql_delete = "
					UPDATE _test_st11 SET
						coop_stats = product_stats
					WHERE number = {$list[$i]['number']}
				";
                query($result);
            }
        }
    }
}