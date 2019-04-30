<?
include_once("{$_SERVER['DOCUMENT_ROOT']}/coop/11st/class.st11.php");

/**
 *
 *
 *                    ####################    11번가 반품배송지 관련 메서드   ####################
 *
 *
 */
class St11SellerAddress extends st11
{
    #
    #
    #
    #       조회기능
    #
    #
    #
    /**등록된 주소지 조회
     * $type : out_address | return_address
     **/
    static function getRegisterdAddress($type)
    {
        $url = ($type == 'out_address') ? 'http://api.11st.co.kr/rest/areaservice/outboundarea' : 'http://api.11st.co.kr/rest/areaservice/inboundarea';
        $method = 'GET';
        $xml = array();
        $response = parent::request($url, $method, $xml);
        $response_array = parent::xml_to_array($response);
        return $response_array['inOutAddress'];
    }


    /**
     * 18.11.23   추가
     * 11번가에 도로명과 지번모두 검색할수 있는 API가 추가됨
     *
     * param
     *      $search_kwd : 검색 키워드  ( 봉은사로 321,  논현동 277-32 )
     */
    static function searchAddress($search_kwd)
    {
        $url = "http://api.11st.co.kr/rest/commonservices/v2/searchAddr";
        $method = "POST";
        $xml_data = array();
        $xml_data['searchRoadAddrKwd'] = $search_kwd['search_addr_kwd'];
        $xml_data['fetchSize'] = $search_kwd['fetch_size'];
        $xml_data['pageNum'] = $search_kwd['page_num'];
        $xml = "<RoadAddrSearchRequest>" . parent::array_to_xml($xml_data) . "</RoadAddrSearchRequest>";
        $response = parent::request($url, $method, $xml);
        $response_array = parent::xml_to_array($response);

        return $response_array;
        #echoDev($response_array);
    }
    /**
     * 도로명주소로 조회
     * **/
    /** param
     * $road_addr
     * ex)  search_road_addr_kwd : 인계로166번길 48-21
     *      sido : 경기도
     *      sigungu : 수원시 팔달구
     *      fetch_size : 100
     *
     * return
     * [areaNo] => 16488
     * [buildMngNO] => 4111514100111190000011524
     * [mailAddr] => 경기도 수원시 팔달구 인계동 1119
     * [mailNO] =>
     * [mailNOSeq] =>
     * [roadAddr] => 인계로166번길 48-21 (인계동,인계샤르망오피스텔)
     * [roadNm] => 인계로166번길
     * [sidoNm] => 경기도
     * [sigunguNm] => 수원시 팔달구
     * [ueupmyonNm] =>
     */
    static function searchAddressByRoadName($road_addr)
    {
        $url = 'http://api.11st.co.kr/rest/commonservices/roadAddr';
        $method = 'POST';
        $xml_data = array();
        $xml_data['searchRoadAddrKwd'] = $road_addr['search_addr_kwd'];
        $xml_data['sido'] = $road_addr['sido'];
        $xml_data['sigungu'] = $road_addr['sigungu'];
        $xml_data['fetchSize'] = $road_addr['fetch_size'];
        $xml = '<RoadAddrSearchRequest>' . parent::array_to_xml($xml_data) . '</RoadAddrSearchRequest>';
        $response = parent::request($url, $method, $xml);
        $response_array = parent::xml_to_array($response);
        return $response_array;
    }
    /**
     * 지번주소 조회
     */
    /** param
     * $jibun_addr
     * ex)
     *      sido : 서울특별시
     *      sigungu : 강남구
     *      search_road_addr_kwd : 논현동
     *      fetch_size : 100
     *
     * return
     * [addr] => 서울특별시 강남구 논현동
     * [mailNO] => 135010
     * [mailNOSeq] => 001
     * [sidoNM] => 서울특별시
     * [sigunguNM] => 강남구
     */
    static function searchAddressByJibun($jibun_addr)
    {
        if (is_array($jibun_addr)) $jibun_addr = $jibun_addr['sido'] . ' ' . $jibun_addr['sigungu'] . ' ' . $jibun_addr['search_addr_kwd'];
        $addr_encoded = urlencode(iconv('euckr', 'utf8', $jibun_addr));
        $url = "http://api.11st.co.kr/rest/commonservices/zipCode/{$addr_encoded}";
        $method = "GET";
        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);
        return $response_array;
    }

    /**
     * 판매자의 db에 입력된 11번가 주소지 등록에 필요한 정보를 가져옴
     * param $user_id : 회원아이디
     * return
     *      addr : 주소
     *      phone : 일반전화번호
     *      hphone : 휴대전화번호
     *      post_no : 우편번호
     *      post... : 우편번호 순번
     *      building_no : 건물관리번호
     *      detail_addr : 상세주소
     */
    static function getSellerData($user_id)
    {
        $user_data = null;
        $seq = null;
    }









    #
    #
    #
    #       조회한 데이터를 정리
    #
    #
    #
    /**
     * 반품주소지를 등록/수정하는 xml생성
     * seq가 있으면 수정, 없으면 등록
     * param
     *   $user_data
     *          user_id : 아이디
     *          user_name : 이름
     *          post_no : 우편번호
     *          post_no_order : 우편번호 순번  ***우편번호,우편번호순번을 입력하면 건물관리번호 입력 x
     *          building_no : 건물관리번호     ***건물관리번호를 입력하면 우편번호,우편번호순번 입력 x
     *          detail_addr : 상세주소
     *          phone : 전화번호
     *          hphone : 핸드폰번호
     *   $seq : 배송지번호(11번가)              ***빈값이면 삽입으로 아니면 수정으로
     */
    static function makeAddressXml($user_data, $seq = null)
    {
        $xml_data = array();
        if ($seq) $xml_data['addrSeq'] = $seq;
        $xml_data['addrNm'] = $user_data['user_id']; # 주소명(아이디)
        $xml_data['rcvrNm'] = $user_data['user_name']; # 이름
        $xml_data['gnrlTlphnNo'] = standardizePhone($user_data['phone']); # 일반전화
        $xml_data['prtblTlphnNo'] = standardizePhone($user_data['hphone']); # 휴대전화
        if ($user_data['post_no']) $xml_data['mailNO'] = $user_data['post_no']; # 우편번호 (선택)
        if ($user_data['post_no_order']) $xml_data['mailNOSeq'] = $user_data['post_no_order']; # 우편번호 순번 (선택)
        if ($user_data['building_no']) $xml_data['buildMngNO'] = $user_data['building_no']; # 건물관리번호 (선택)
        if ($user_data['addr_ord']) $xml_data['lnmAddrSeq'] = $user_data['addr_ord']; # 지번순번 (선택)
        $xml_data['dtlsAddr'] = $user_data['detail_addr']; # 상세주소
        #$xml_data['addrClfCd'] = ($user_data['building_no']) ? '01' : '02';
        $xml = '<InOutAddress>' . parent::array_to_xml($xml_data) . '</InOutAddress>';
        return $xml;
    }







    #
    #
    #
    #       정리한 데이터를 동기화하는 기능
    #
    #
    #
    /**
     * 반품주소지를 수동 등록/수정
     * coop_value가 있으면 수정, 없으면 등록
     * param
     *   $user_data
     *          coop_value : 주소순번(11번가)  **수정이면 넣고 삽입이면 넣지않음
     *          user_id : 아이디
     *          user_name : 이름
     *          post_no : 우편번호
     *          post_no_order : 우편번호 순번  ***우편번호,우편번호순번을 입력하면 건물관리번호 입력 x
     *          building_no : 건물관리번호     ***건물관리번호를 입력하면 우편번호,우편번호순번 입력 x
     *          detail_addr : 상세주소
     *          phone : 전화번호
     *          hphone : 핸드폰번호
     *
     * $address_type : 'return_address' or 'out_address'
     */
    static function syncSellerAddressManual($user_data, $address_type)
    {
        switch ($address_type) {
            case 'return_address': #반품교환지
                $add_url = 'http://api.11st.co.kr/rest/areaservice/registerRtnAddress';
                $mod_url = 'http://api.11st.co.kr/rest/areaservice/updateRtnAddress';
                #$add_url = 'http://api.11st.co.kr/rest/areaservice/v2/registerRtnAddress';
                #$mod_url = 'http://api.11st.co.kr/rest/areaservice/v2/updateRtnAddress';
                break;
            case 'out_address': #출고지
                $add_url = 'http://api.11st.co.kr/rest/areaservice/registerOutAddress';
                $mod_url = 'http://api.11st.co.kr/rest/areaservice/updateOutAddress';
                #$add_url = 'http://api.11st.co.kr/rest/areaservice/v2/registerRtnAddress';
                #$mod_url = 'http://api.11st.co.kr/rest/areaservice/v2/updateRtnAddress';
                break;
            default:
                return array('result' => '등록되지 않은 address_type입니다.', 'result_bool' => false);
                break;
        }
        $seq = $user_data['coop_value'];
        #등록/수정 구분
        $url = (empty($seq)) ? $add_url : $mod_url;
        $mode = (empty($seq)) ? 'add' : 'mod';
        $method = 'POST';

        $xml_data = self::makeAddressXml($user_data, $seq);
        $response = parent::request($url, $method, $xml_data);
        $response_array = parent::xml_to_array($response);

        $return = array();
        $return['coop_name'] = '11st.' . $address_type;
        $return['reg_date'] = $GLOBALS['YMDHIS'];
        $return['user_id'] = $user_data['user_id'];

        #성공/실패 구분
        #isset($response_array['inOutAddress']) -> true -> 성공한거임
        $return['result'] = (isset($response_array['inOutAddress'])) ? $response_array['inOutAddress']['addrSeq'] : $response_array['result_message'];
        $return['result_bool'] = (isset($response_array['inOutAddress'])) ? true : false;

        #coop_selle_address에 결과 넣음
        self::updateCoopSellerAddress($return, $mode);
        return $return;
    }

    /**
     * 반품교환지/출고지 동기화
     */
    /*static function syncSellerAddress($user_id, $seq = null)
    {
    }*/

    /**
     * 개발자용
     */
    /*static function getSellerAddresManual($user_id)
    {
    }*/


    #
    #
    #
    #   동기화후 처리
    #
    #
    #
    /**
     * 동기화후 반환된 데이터로 (제휴반품배송지테이블)에 결과를 넣는다
     * param
     *  $data
     *      user_id : 아이디
     *      coop_name : 11st.return_address || 11st.out_address
     *      result_bool : true || false
     *      result : 반환된 제휴사값 || 에러메세지
     *      reg_date : 현재시간
     *  $mode : add || mod
     */
    static function updateCoopSellerAddress($data, $mode)
    {
        $sql = "SELECT * FROM (제휴반품배송지테이블) WHERE user_id = '{$data['user_id']}' AND coop_name = '{$data['coop_name']}'";
        list($CSA_data) = util::query_to_array($sql);

        if ($CSA_data) { #수정 동기화, 신규동기화 실패후 재동기화
            if ($mode == 'add')
                $status = ($data['result_bool']) ? '정상' : '신규동기화실패';
            elseif ($mode == 'mod')
                $status = ($data['result_bool']) ? '정상' : '수정동기화실패';

            $update_coop_value = ($data['result_bool']) ? ", coop_value = '" . $data['result'] . "'" : '';
            $sql = "
                UPDATE (제휴반품배송지테이블)
                SET    sync_date = '{$data['reg_date']}'
                ,      status = '{$status}'
                {$update_coop_value}
                WHERE  number = '{$CSA_data['number']}'
                ";
            query($sql);
        } else { #첫 동기화
            $CSA = array();
            $CSA['user_id'] = $data['user_id'];
            $CSA['coop_name'] = $data['coop_name'];
            $CSA['coop_value'] = ($data['result_bool']) ? $data['result'] : '';
            $CSA['status'] = ($data['result_bool']) ? '정상' : '신규동기화실패';
            $CSA['reg_date'] = $data['reg_date'];
            util::insert_array('(제휴반품배송지테이블)', $CSA);
        }
        #echoDev($CSA_data,$sql);
    }


    /**
     * $type : return_address  ||  out_address
     * (제휴반품배송지테이블)의 등록이 안된 판매자들을 동기화 예정으로 바꾼다.
     *
     * 11번가와 지마켓은 자동처리가 안되게때문에 '동기화요청필요'라고 한다
     * coop_Seller_address에 등록되지않은 판매중 상품이 1개 이상인 사람들
     */
    public function crontab_needSync($type)
    {
        if (!preg_match('/out_address|return_address/', $type)) return false;

        $sql = "
            SELECT  CSA.user_id
            FROM    (제휴반품배송지테이블) AS CSA
            WHERE   CSA.coop_name LIKE ('11st.{$type}')
        ";
        $coop_id_list = util::query_to_array($sql);
        $coop_id_list = util::array_column($coop_id_list, 'user_id');
        $coop_id_list = "'" . implode("','", $coop_id_list) . "'";


        #echoDev($sql,$pk_id_list);
        $sql = "
            SELECT   HM.user_id
            FROM (상품테이블) AS AP
            LEFT OUTER JOIN (회원테이블) AS HM ON HM.user_id = AP.id
            WHERE HM.user_id IS NOT NULL
            AND AP.product_stats = '0'
            AND HM.user_id NOT IN ({$coop_id_list})
            GROUP BY HM.user_id
        ";
        $id_list = util::query_to_array($sql);
        $id_list = util::array_column($id_list, 'user_id');
        if ($GLOBALS['print_process']) echoDev("아래 쿼리후 남은 회원수 : " . count($id_list), $sql);

        $CSA = array();
        for ($i = 0; $i < count($id_list); $i++) {
            $row = $id_list[$i];
            $CSA_row = array();
            $CSA_row['user_id'] = $row;
            $CSA_row['coop_name'] = '11st.' . $type;
            $CSA_row['status'] = '동기화요청필요';
            $CSA_row['reg_date'] = $GLOBALS['YMDHIS'];
            $CSA[] = $CSA_row;
        }
        echoDev($sql, $id_list);
        util::insert_multi_array('(제휴반품배송지테이블)', $CSA);
    }


    public function test($type)
    {
        if (!preg_match('/out_address|return_address/', $type)) return false;

        $sql = "
            SELECT  CD.pk_id
            FROM    coop_data AS CD
            WHERE   CD.`from` LIKE ('11st')
            AND     CD.column_id = '{$type}'
        ";
        $coop_id_list = util::query_to_array($sql);
        $coop_id_list = util::array_column($coop_id_list, 'pk_id');
        $coop_id_list = "'" . implode("','", $coop_id_list) . "'";


        #echoDev($sql,$pk_id_list);
        $sql = "
            SELECT   HM.user_id
            FROM (상품테이블) AS AP
            LEFT OUTER JOIN (회원테이블) AS HM ON HM.user_id = AP.id
            WHERE HM.user_id IS NOT NULL
            AND AP.product_stats = '0'
            AND HM.number NOT IN ({$coop_id_list})
            GROUP BY HM.user_id
        ";
        $id_list = util::query_to_array($sql);
        $id_list = util::array_column($id_list, 'user_id');
        if ($GLOBALS['print_process']) echoDev("아래 쿼리후 남은 회원수 : " . count($id_list), $sql);

        $CSA = array();
        for ($i = 0; $i < count($id_list); $i++) {
            $row = $id_list[$i];
            $CSA_row = array();
            $CSA_row['user_id'] = $row;
            $CSA_row['coop_name'] = '11st.' . $type;
            $CSA_row['status'] = '동기화요청필요';
            $CSA_row['reg_date'] = $GLOBALS['YMDHIS'];
            $CSA[] = $CSA_row;
        }
        echoDev($sql, $id_list);
        #util::insert_multi_array('(제휴반품배송지테이블)',$CSA);
    }
}


/**
 *
 *
 *
 *              ####   기존에 개발되어있던 반품배송지 관련 클래스   ####
 *              (사용하지 않는거 같음)
 *
 *
 */
class st11_address extends st11
{
    public function __construct($user_id)
    {
        $this->user_id = $user_id;
    }

    /**
     * 출고지, 반품/교환지 신규 등록
     * addr_data를 수동입력할 수 있습니다
     * 상세내용은 11st/add_seller_address_manual.php를 참조
     */
    public function add_address($addr_data = null)
    {
        $member_data = $this->get_member_data();
        $reg_date = date('Y-m-d H:i:s');

        if ($addr_data == null) {
            $xml = $this->make_address_xml();
            if ($xml == false) return false;
        } else {
            # 주소명
            $xml_data['addrNm'] = $member_data['user_id'];
            # 이름
            $xml_data['rcvrNm'] = $member_data['user_name'];
            # 일반전화
            $xml_data['gnrlTlphnNo'] = standardizePhone($member_data['user_phone']);
            # 휴대전화
            $xml_data['prtblTlphnNo'] = standardizePhone($member_data['user_hphone']);
            # 우편번호
            $xml_data['mailNO'] = $addr_data['mailNO'];
            # 우편번호 순번
            $xml_data['mailNOSeq'] = $addr_data['mailNOSeq'];
            # 상세주소
            $xml_data['dtlsAddr'] = $addr_data['dtlsAddr'];
            $xml = '<InOutAddress>' . parent::array_to_xml($xml_data) . '</InOutAddress>';
        }
        $method = 'POST';

        # 출고지등록
        $url = 'http://api.11st.co.kr/rest/areaservice/registerOutAddress';
        $response = parent::request($url, $method, $xml);
        $OA = parent::xml_to_array($response);
        /*
        RESPONSE 데이터
        Array
        (
            [inOutAddress] => Array
                (
                    [addrNm] => qudghk1219
                    [addrSeq] => 99
                    [dtlsAddr] => 테스트 123호
                    [gnrlTlphnNo] => 010-5048-3245
                    [mailNO] => 135010
                    [mailNOSeq] => 001
                    [prtblTlphnNo] => 010-5048-3245
                    [rcvrNm] => 유병화
                )

            [result_message] => SUCCESS
        )
        */
        # 성공
        if ($OA['result_message'] == 'SUCCESS') {
            $insert_coop_data = array();
            $insert_coop_data['pk_id'] = $member_data['number'];
            $insert_coop_data['column_id'] = 'out_address';
            $insert_coop_data['value'] = $OA['inOutAddress']['addrSeq'];
            $insert_coop_data['from'] = '11st';
            $insert_coop_data['reg_date'] = $reg_date;
            util::insert_array('coop_data', $insert_coop_data);
        } # 실패
        else {
            echoDev($OA);
            return false;
        }


        # 반품/교환지 등록
        $url = 'http://api.11st.co.kr/rest/areaservice/registerRtnAddress';
        $response = parent::request($url, $method, $xml);
        $RA = parent::xml_to_array($response);
        # 성공
        if ($RA['result_message'] == 'SUCCESS') {
            $insert_coop_data = array();
            $insert_coop_data['pk_id'] = $member_data['number'];
            $insert_coop_data['column_id'] = 'return_address';
            $insert_coop_data['value'] = $RA['inOutAddress']['addrSeq'];
            $insert_coop_data['from'] = '11st';
            $insert_coop_data['reg_date'] = $reg_date;
            util::insert_array('coop_data', $insert_coop_data);
        } # 실패
        else {
            return false;
        }

        return true;
    }

    /**
     * 출고지, 반품/교환지 수정
     */
    public function edit_address()
    {
        $member_data = $this->get_member_data();
        $method = 'POST';
        /*
        RESPONSE 데이터
        Array
        (
            [inOutAddress] => Array
                (
                    [addrNm] => qudghk1219
                    [addrSeq] => 99
                    [dtlsAddr] => 테스트 123호
                    [gnrlTlphnNo] => 010-5048-3245
                    [mailNO] => 135010
                    [mailNOSeq] => 001
                    [prtblTlphnNo] => 010-5048-3245
                    [rcvrNm] => 유병화
                )
            [result_message] => SUCCESS
        )
        */
        # 출고지수정
        $url = 'http://api.11st.co.kr/rest/areaservice/updateOutAddress';
        $xml = $this->make_address_xml($member_data['out_addr_seq']);
        $response = self::request($url, $method, $xml);
        $OA = self::xml_to_array($response);
        # 성공
        if ($OA['result_message'] == 'SUCCESS') {
        } # 실패
        else {
        }
        # 반품/교환지 수정
        $url = 'http://api.11st.co.kr/rest/areaservice/updateRtnAddress';
        $xml = $this->make_address_xml($member_data['return_addr_seq']);
        $response = self::request($url, $method, $xml);
        $RA = self::xml_to_array($response);
        # 성공
        if ($RA['result_message'] == 'SUCCESS') {
        } # 실패
        else {
        }
    }

    /**
     * 회원정보 반환
     */
    public function get_member_data()
    {
        if ($this->member_data) return $this->member_data;
        $sql = "
			SELECT	HM.number,
					HM.user_id,
					HM.user_name,
					HM.user_phone,
					HM.user_hphone,
					HM.user_zip,
					HM.user_addr1,
					HM.user_addr1_2,
					HM.user_addr2,
					CDO.number AS out_addr_num,
					CDO.value AS out_addr_seq,
					CDR.number AS return_addr_num,
					CDR.value AS return_addr_seq
			FROM	happy_member AS HM
			LEFT OUTER JOIN coop_data AS CDO ON CDO.pk_id = HM.number AND CDO.`from` = '11st' AND CDO.column_id = 'out_address'
			LEFT OUTER JOIN coop_data AS CDR ON CDR.pk_id = HM.number AND CDR.`from` = '11st' AND CDR.column_id = 'return_address'
			WHERE	user_id = '{$this->user_id}'
		";
        $this->member_data = mysql_fetch_assoc(query($sql));
        return $this->member_data;
    }

    /**
     * 출고지, 반품/교환지 주소를
     * 등록/수정하는 XML을 생성합니다
     * $seq은 coop_data의 value값입니다.
     * $seq가 있으면 수정모드, 없으면 등록모드입니다
     */
    public function make_address_xml($seq = '')
    {
        # 회원데이터
        $member_data = $this->get_member_data();
        # 우편번호데이터
        $zip_code_data = $this->search_zip_code_by_jibun();

        if ($zip_code_data == false) return false;

        # XML 작성 시작
        $xml_data = array();
        # 수정모드일 경우 수정할 주소지 번호
        if ($seq != '') $xml_data['addrSeq'] = $seq;
        # 주소명
        $xml_data['addrNm'] = $member_data['user_id'];
        # 이름
        $xml_data['rcvrNm'] = $member_data['user_name'];
        # 일반전화
        $xml_data['gnrlTlphnNo'] = standardizePhone($member_data['user_phone']);
        # 휴대전화
        $xml_data['prtblTlphnNo'] = standardizePhone($member_data['user_hphone']);
        # 우편번호
        $xml_data['mailNO'] = $zip_code_data['mailNO'];
        # 우편번호 순번
        $xml_data['mailNOSeq'] = $zip_code_data['mailNOSeq'];
        # 상세주소
        $xml_data['dtlsAddr'] = $zip_code_data['detail'];
        $xml = '<InOutAddress>' . parent::array_to_xml($xml_data) . '</InOutAddress>';
        return $xml;
    }

    /**
     * 자사 주소데이터를 이용하여
     * 11번가 우편번호 및 우편번호 시퀀스를 검색합니다
     * 1) 11st_zip_code에서 동일한 우편번호를 검색합니다. 6자리 구우편번호만 지원합니다.
     * 2) 우편번호 검색결과가 1행이라면 해당 우편번호와 시퀀스를 반환합니다.
     * 3) 우편번호 검색결과가 2행 이상이라면 판매자의 지번주소를 확인하여, 해당 지번주소에 맞는 우편번호를 반환합니다
     * 4) 3단계까지 반환 실패시, 11번가 도로명 주소 검색 API를 이용해 주소를 검색합니다
     * 5) API주소검색 결과가 1행이라면 해당 우편번호와 시퀀스를 반환합니다
     * 6) API주소검색 결과가 2행 이상이라면 수동으로 매칭할 수 있도록 기록을 남깁니다
     */
    public function search_11st_zip_code()
    {
        $member_data = $this->get_member_data();
        if ($member_data['user_zip'] == '') return false;
        # 우편번호를 숫자만 남깁니다
        $zip_code = preg_replace("/[^\d]/", '', $member_data['user_zip']);

        # 우편번호가 6자리일 경우 DB에서 검색합니다.
        if (strlen($zip_code) == 6) {
            $sql = "
				SELECT	*
				FROM	11st_zip_code
				WHERE	zip_code = {$zip_code}
			";
            $address = util::query_to_array($sql);
            # DB 검색결과가 1개일 경우 해당 데이터를 사용합니다
            if (count($address) == 1) {
                return $address[0];
            } else {
            }
        }


        return false;


        # 구우편번호(6자리)가 아니면 : 11번가 API를 이용해 도로명 주소 검색을 시도합니다
        if (strlen($zipcode) != 6) return $this->search_11st_zip_code_by_road_name();
        $sql = "
			SELECT		*
			FROM		11st_zip_code
			WHERE		zip_code = {$zip_code}
		";
        $data = util::query_to_array($sql);
        # 하나만 검색되었을 경우 해당 데이터를 반환합니다
        if (count($data) == 1) return $data[0];

        # 여러개가 검색되었을 경우
        # 회원정보에 지번주소가 있으면 : 지번주소를 분석하여 검색합니다
        if ($member_data['user_addr1'] != '') {
            #self::analysis_ 개발중
        }
    }

    /**
     * 11번가 API를 이용해 도로명 주소 검색
     */
    public function search_11st_zip_code_by_road_name()
    {
        return '개발예정';
    }


    /**
     * 출고지 조회(전체)
     */
    static function get_outboundarea()
    {
        $url = 'http://api.11st.co.kr/rest/areaservice/outboundarea';
        $method = 'GET';
        return self::request($url, $method);
    }

    /**
     * 출고지 조회(시퀀스번호필요)
     */
    static function getOutAddressInfo($addrSeq)
    {
        $url = "http://api.11st.co.kr/rest/areaservice/getOutAddressInfo/{$addrSeq}";
        $method = 'GET';
        return self::request($url, $method);
    }

    /**
     * 반품교환지 조회(시퀀스번호필요)
     */
    static function getReturnAddressInfo($addrSeq)
    {
        $url = "http://api.11st.co.kr/rest/areaservice/getRtnAddressInfo/${addrSeq}";
        $method = 'GET';
        return self::request($url, $method);
    }


    /**
     * 지번주소를 파싱하여 11번가 우편번호를 반환합니다
     */
    public function search_zip_code_by_jibun()
    {
        $member = $this->get_member_data();
        if ($member['user_addr1'] == '') return false;

        # 주소를 배열화
        $address = array_values(array_filter(explode(' ', "{$member['user_addr1']} {$member['user_addr2']}")));

        /**
         * 검색을 위해 배열화된 주소를 head middle tail로 나눕니다
         * addr_head : 시도 시군구 읍면동
         * addr_middle : 번지수 또는 아파트명
         * addr_tail : 호수 또는 상호 등 상세주소
         */
        $addr_head = array();
        $addr_middle = '';
        $addr_tail = array();

        # 시도명 표준화
        switch (true) {
            case is_numeric(strpos('서울', $address[0])) :
                $addr_head[] = '서울특별시';
                break;
            case is_numeric(strpos('부산', $address[0])) :
                $addr_head[] = '부산광역시';
                break;
            case is_numeric(strpos('대구', $address[0])) :
                $addr_head[] = '대구광역시';
                break;
            case is_numeric(strpos('인천', $address[0])) :
                $addr_head[] = '인천광역시';
                break;
            case is_numeric(strpos('광주', $address[0])) :
                $addr_head[] = '광주광역시';
                break;
            case is_numeric(strpos('대전', $address[0])) :
                $addr_head[] = '대전광역시';
                break;
            case is_numeric(strpos('울산', $address[0])) :
                $addr_head[] = '울산광역시';
                break;
            case is_numeric(strpos('세종', $address[0])) :
                $addr_head[] = '세종특별자치시';
                break;
            case is_numeric(strpos('경기', $address[0])) :
                $addr_head[] = '경기도';
                break;
            case is_numeric(strpos('충북', $address[0])) :
                $addr_head[] = '충청북도';
                break;
            case is_numeric(strpos('충남', $address[0])) :
                $addr_head[] = '충청남도';
                break;
            case is_numeric(strpos('전북', $address[0])) :
                $addr_head[] = '전라북도';
                break;
            case is_numeric(strpos('전남', $address[0])) :
                $addr_head[] = '전라남도';
                break;
            case is_numeric(strpos('경북', $address[0])) :
                $addr_head[] = '경상북도';
                break;
            case is_numeric(strpos('경남', $address[0])) :
                $addr_head[] = '경상남도';
                break;
            case is_numeric(strpos('제주', $address[0])) :
                $addr_head[] = '제주특별자치도';
                break;
        }
        # 도 단위일 경우(경기도 수원시 팔달구 인계동)
        if (mb_substr($addr_head[0], -1, 1, 'euckr') == '도') {
            $head_length = 4;
        } # 시 단위일 경우(서울특별시 강남구 논현동)
        else {
            $head_length = 3;
        }
        # head, middle, tail 준비
        for ($i = 1, $end = count($address); $i < $end; ++$i) {
            if ($i < $head_length) {
                $addr_head[] = $address[$i];
                # 읍면동은 별도로 저장
                if ($i == $head_length - 1) $addr_head_last = $address[$i];
            } elseif ($i == $head_length) {
                $addr_middle = $address[$i];
            } else {
                $addr_tail[] = $address[$i];
            }
        }
        $addr_head = implode(' ', $addr_head);
        $addr_tail = implode(' ', $addr_tail);

        # addr_middle 표준화(n, n번지, n-n, n-n번지, s아파트)
        if ($addr_middle == '') return false;
        if (strpos('아파트', $addr_middle)) {
            $middle_is_apt = true;
        } else {
            $addr_middle = str_replace('번지', '', $addr_middle);
            list($bunji) = explode('-', $addr_middle);
            $addr_middle = "{$addr_middle}번지";
            # 번지로 추정되는 문자가 숫자가 아닐경우 false
            if (is_numeric($bunji) == false) return false;
        }

        # 11번가 우편번호조회 검색어
        if ($middle_is_apt) {
            $search_string = "{$addr_head} {$addr_middle}";
        } else {
            $search_string = $addr_head;
        }

        # 검색 실행
        $search_string = urlencode(iconv('euckr', 'utf8', $search_string));
        $url = "http://api.11st.co.kr/rest/commonservices/zipCode/{$search_string}";
        $method = 'GET';
        $response = parent::request($url, $method);
        $response_array = parent::xml_to_array($response);

        if (isset($response_array['zipCode'][0])) {
            $response_array = $response_array['zipCode'];
        } elseif (count($response_array)) {
            $response_array = array($response_array['zipCode']);
        } else {
            return false;
        }


        # 검색 결과가 단건일 경우
        $response_count = count($response_array);
        if ($response_count == 1) {
            # 아파트 검색결과일 경우 detail은 addr_tail만 사용
            if ($middle_is_apt) {
                $response_array[0]['detail'] = $addr_tail;
            } # 지번 검색결과일경우 detail은 addr_middle과 addr_tail을 함께 사용
            else {
                $response_array[0]['detail'] = $addr_middle;
                if ($addr_tail) $response_array[0]['detail'] .= " " . $addr_tail;
            }
            return $response_array[0];
        }

        # 검색 결과가 복수건이고 addr_middle이 아파트일경우
        if ($middle_is_apt) {
            for ($i = 0; $i < $response_count; ++$i) {
                $row = $response_array[$i];
                # 띄어쓰기를 기준으로 마지막 단어 추출
                $last_word = substr($row['addr'], strrpos($row['addr'], ' ') + 1);
                # 마지막 단어가 읍면동인 케이스 별도 보관
                if ($last_word == $addr_head_last) {
                    $default_addr = $row;
                    $default_addr['detail'] = $addr_middle;
                    if ($addr_tail) $default_addr['detail'] .= " " . $addr_tail;
                }
                # 아파트 이름이 일치할 경우
                if ($last_word == $addr_middle) {
                    $row['detail'] = $addr_tail;
                    return $row;
                }
            }
            # 아파트 이름이 일치하는 케이스가 없으면 읍면동으로 끝나는 주소로 반환
            return $default_addr;
        } # 검색 결과가 복수건이고 addr_middle이 번지일경우
        elseif ($bunji) {
            for ($i = 0; $i < $response_count; ++$i) {
                $row = $response_array[$i];
                # 띄어쓰기를 기준으로 마지막 단어 추출( n~n )
                $last_word = substr($row['addr'], strrpos($row['addr'], ' ') + 1);
                # 마지막 단어가 읍면동인 케이스 별도 보관
                if ($last_word == $addr_head_last) {
                    $default_addr = $row;
                    $default_addr['detail'] = $addr_middle;
                    if ($addr_tail) $default_addr['detail'] .= " " . $addr_tail;
                }
                # 마지막 단어를 ~을 기준으로 분리
                $last_word = explode('~', $last_word);
                # 둘 중 하나라도 숫자가 아니면 continue
                if (is_numeric($last_word[0]) == false || is_numeric($last_word[1]) == false) continue;
                # 번지수 범위 내에 포함될 경우
                if ($last_word[0] < $bunji && $bunji < $last_word[1]) {
                    $row['detail'] = $addr_middle;
                    if ($addr_tail) $row['detail'] .= " " . $addr_tail;
                    return $row;
                }
            }
            # 번지 범위에 포함된 케이스가 없으면 읍면동으로 끝나는 주소로 반환
            return $default_addr;
        }
    }


    /**
     * 도로명주소로 검색
     * 시도, 검색어만 입력해도 검색이 된다.
     * 우편번호 순번을 이걸로 조회해야겠다.
     */
    static function search_address_by_road_name()
    {
        $url = 'http://api.11st.co.kr/rest/commonservices/roadAddr';
        $method = 'POST';

        $xml_data = array();
        $xml_data['searchRoadAddrKwd'] = '인계로166번길 48-21';
        $xml_data['sido'] = '경기도';
        $xml_data['sigungu'] = '수원시 팔달구';
        #$xml_data['fetchSize'] = '100';
        $xml = '<RoadAddrSearchRequest>' . self::array_to_xml($xml_data) . '</RoadAddrSearchRequest>';

        return self::request($url, $method, $xml);
    }

    /**
     * 우편번호조회
     * '압구정로'로 검색하면 결과없음.
     * '압구정'으로 검색하면 결과가 있다.
     * 우편번호, 지번주소, 우편번호 순번이 반환된다.
     */
    static function search_zip_code($address_name)
    {
        $address_name_encoded = urlencode(iconv('euckr', 'utf8', $address_name));
        $url = "http://api.11st.co.kr/rest/commonservices/zipCode/{$address_name_encoded}";
        return self::request($url, 'GET');
    }

    /**
     * 도로명주소 추천검색
     * 검색결과가 안나와서 사용보류
     */
    static function search_address_by_jibun()
    {
        $url = 'http://api.11st.co.kr/rest/commonservices/roadAddrSuggest';
        $method = 'POST';

        $xml_data = array();
        $xml_data['searchRoadAddrKwd'] = '';
        $xml_data['sido'] = '경기도';
        $xml_data['sigungu'] = '수원시 팔달구';
        $xml_data['ueupmyeon'] = '인계동';
        $xml_data['fetchSize'] = '10';
        $xml_data['pageNum'] = '1';
        $xml = '<RoadAddrSearchRequest>' . self::array_to_xml($xml_data) . '</RoadAddrSearchRequest>';

        return self::request($url, $method, $xml);
    }
}
