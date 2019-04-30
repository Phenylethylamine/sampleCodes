<?php

class FirebaseCloudMessageBuilder
{
    public $message;
    public $presetName;

    public function __construct($presetName = '', $presetParam = null)
    {
        $this->presetName = $presetName;
        $this->message = ($presetName) ? $this->getPreset($presetName, $presetParam) : array();
        $this->message['registration_ids'] = array();
    }

    public function getPreset($presetName, $presetParam = null)
    {

        if (empty($presetParam)) $presetParam = array();

        $message = array();
        $message['collapse_key'] = $presetName;
        $message['data'] = array();
        $message['data']['onClickNotification'] = $presetName;
        switch ($presetName) {
            case 'MessageActivity' :
                $message['priority'] = 'high';
                $message['data']['messageStructure'] = 'dataNotification';
                $message['data']['responseOnMessageReceived'] = '0';
                $message['data']['responseOnClickNotification'] = '0';
                $message['data']['MessageActivityPartnerId'] = $presetParam['partnerId'];

                $message['data']['notificationTitle'] = '쪽지 알림';
                $message['data']['notificationText'] = '쪽지가 왔습니다. 터치하여 확인해주세요.';
                $message['data']['notificationIcon'] = 'ic_stat_ic_mainicon_noti_2';
                $message['data']['notificationLargeIcon'] = 'ic_pricegolf_noti';
                break;

            case 'QnaActivity' :
                $message['priority'] = 'high';
                $message['data']['messageStructure'] = 'dataNotification';
                $message['data']['responseOnMessageReceived'] = '0';
                $message['data']['responseOnClickNotification'] = '0';
                $message['data']['QnaActivityMode'] = $presetParam['mode']; # 'Q' or 'A'

                if ($presetParam['mode'] == 'Q') {
                    $message['data']['notificationTitle'] = '상품 문의';
                    $message['data']['notificationText'] = '판매자님 상품 문의가 들어왔습니다.';
                    $message['data']['notificationIcon'] = 'ic_stat_ic_mainicon_noti_2';
                    $message['data']['notificationLargeIcon'] = 'ic_qna_q';
                } elseif ($presetParam['mode'] == 'A') {
                    $message['data']['notificationTitle'] = '상품 문의 답변';
                    $message['data']['notificationText'] = '고객님 상품문의에 답변이 작성되었습니다.';
                    $message['data']['notificationIcon'] = 'ic_stat_ic_mainicon_noti_2';
                    $message['data']['notificationLargeIcon'] = 'ic_qna_a2';
                }
                break;

            case 'NotiMyPrdActivity' :
                $message['priority'] = 'high';
                $message['data']['messageStructure'] = 'dataNotification';
                $message['data']['responseOnMessageReceived'] = '0';
                $message['data']['responseOnClickNotification'] = '0';
                $message['data']['notiMyPrdActivityNumber'] = $presetParam['number'];

                $message['data']['notificationTitle'] = '상품 등록 알리미';
                $message['data']['notificationText'] = "[{$presetParam['searchWord']}] - {$presetParam['count']} 개의 상품이 등록되었습니다.";
                $message['data']['notificationIcon'] = 'ic_stat_ic_mainicon_noti_2';
                $message['data']['notificationLargeIcon'] = 'ic_pricegolf_noti';
                break;

            case 'MypageActivity' :
                $message['priority'] = 'high';
                $message['data']['messageStructure'] = 'dataNotification';
                $message['data']['responseOnMessageReceived'] = '0';
                $message['data']['responseOnClickNotification'] = '0';
                $message['data']['mypageActivityMode'] = $presetParam['mode']; #'S' or 'B';
                $message['data']['mypageActivityStats'] = $presetParam['stats']; # $jangproductStats;

                $message['data']['notificationIcon'] = 'ic_stat_ic_mainicon_noti_2';
                $message['data']['notificationLargeIcon'] = 'ic_pricegolf_noti';
                switch ($presetParam['mode']) {
                    case 'S' :
                        switch ($presetParam['stats']) {
                            case '3' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '상품 판매 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '등록한 상품이 판매되었습니다.';
                                break;
                            case '7' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '구매 취소 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '구매자가 구매를 취소하였습니다.';
                                break;
                            case '8' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '반품 신청 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '구매자가 반품을 신청하였습니다.';
                                break;
                            case '17' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '교환 신청 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '구매자가 교환을 신청하였습니다.';
                                break;
                        }
                        break;
                    case 'B' :
                        switch ($presetParam['stats']) {
                            case '3' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '결제 완료 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '결제가 완료되었습니다. 판매자가 상품을 발송할 예정입니다.';
                                break;
                            case '4' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '상품 발송 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '구매한 상품의 배송이 시작되었습니다.';
                                break;
                            case '11' :
                                $message['data']['notificationTitle'] = !empty($presetParam['title']) ? $presetParam['title'] : '판매 취소 알림';
                                $message['data']['notificationText'] = !empty($presetParam['text']) ? $presetParam['text'] : '판매자가 주문하신 상품의 판매를 취소하였습니다.';
                                break;
                        }
                        break;
                }
                break;


            case 'EventBbsListActivity' :
                $message['priority'] = 'normal';
                $message['data']['messageStructure'] = 'dataNotification';
                $message['data']['responseOnMessageReceived'] = '1';
                $message['data']['responseOnClickNotification'] = '1';

                $message['data']['notificationTitle'] = $presetParam['title'];
                $message['data']['notificationText'] = $presetParam['text'];
                #$message['data']['notificationIcon'] = '';
                break;
            case 'ProductActivity' :
                $message['priority'] = 'normal';
                $message['data']['messageStructure'] = 'dataNotification';
                $message['data']['responseOnMessageReceived'] = '1';
                $message['data']['responseOnClickNotification'] = '1';
                $message['data']['productActivityNumber'] = $presetParam['productNumber'];

                $message['data']['notificationTitle'] = $presetParam['title'];
                $message['data']['notificationText'] = $presetParam['text'];
                #$message['data']['notificationIcon'] = '';
                break;
            case 'LogoutTask' :
                $message['priority'] = 'normal';
                $message['data']['messageStructure'] = 'dataOnly';
                $message['data']['responseOnMessageReceived'] = '1';
                $message['data']['responseOnClickNotification'] = '0';
                break;
//            case 'RegistAndroidUserTask' :
//                $message['priority'] = 'normal';
//                $message['data']['messageStructure'] = 'dataOnly';
//                $message['data']['responseOnMessageReceived'] = '1';
//                $message['data']['responseOnClickNotification'] = '0';
//                break;
        }
        return $message;
    }

    public function addTargetById($userId, $ignoreAgree = false)
    {
        if (empty($userId)) return $this;

        if (is_array($userId)) {
            $userIds = "'" . implode("','", $userId) . "'";
        } else {
            $userIds = "'{$userId}'";
        }

        $where = array();
        $where[] = "firebase_instance_id IS NOT NULL";
        $where[] = "logged_id IN ({$userIds})";
        if (!$ignoreAgree) {
            if (in_array($this->presetName, array('MessageActivity', 'QnaActivity', 'MypageActivity', 'NotiMyPrdActivity'))) {
                $where[] = "common_push_agree IS NOT NULL";
            } elseif (in_array($this->presetName, array('EventBbsListActivity', 'ProductActivity'))) {
                $where[] = "marketing_push_agree IS NOT NULL";
            }
        }

        $where = implode(' AND ', $where);
        $sql = "
            SELECT      firebase_instance_id
            FROM        (안드로이드유저테이블)
            WHERE       {$where}
        ";
        $data = util::query_to_array($sql);
        $this->message['registration_ids'] = array_merge($this->message['registration_ids'], util::array_column($data, 'firebase_instance_id'));

        return $this;
    }

    public function addTargetByPhoneNumber($phoneNumber, $ignoreAgree = false)
    {
        if (empty($phoneNumber)) return $this;

        $notDigitsPattern = "/\D/";
        if (is_array($phoneNumber)) {
            for ($i = 0, $end = count($phoneNumber); $i < $end; ++$i) {
                $phoneNumber[$i] = preg_replace($notDigitsPattern, '', $phoneNumber[$i]);
            }
            $phoneNumbers = "'" . implode("','", $phoneNumber) . "'";
        } else {
            $phoneNumbers = "'" . preg_replace($notDigitsPattern, '', $phoneNumber) . "'";
        }

        $where = array();
        $where[] = "firebase_instance_id IS NOT NULL";
        $where[] = "phone_number IN ({$phoneNumbers})";
        if (!$ignoreAgree) {
            if (in_array($this->presetName, array('MessageActivity', 'QnaActivity', 'MypageActivity', 'NotiMyPrdActivity'))) {
                $where[] = "common_push_agree IS NOT NULL";
            } elseif (in_array($this->presetName, array('EventBbsListActivity', 'ProductActivity'))) {
                $where[] = "marketing_push_agree IS NOT NULL";
            }
        }

        $where = implode(' AND ', $where);
        $sql = "
            SELECT      firebase_instance_id
            FROM        (안드로이드유저테이블)
            WHERE       {$where}
        ";
        $data = util::query_to_array($sql);
        $this->message['registration_ids'] = array_merge($this->message['registration_ids'], util::array_column($data, 'firebase_instance_id'));

        return $this;
    }

    public function addTargetByFirebaseInstanceId($firebaseInstanceId)
    {
        if (empty($firebaseInstanceId)) return $this;

        if (is_array($firebaseInstanceId)) {
            $this->message['registration_ids'] = array_merge($this->message['registration_ids'], $firebaseInstanceId);
        } else {
            $this->message['registration_ids'][] = $firebaseInstanceId;
        }
        return $this;
    }

    public function addTargetByAgree($agreeType = 'CommonPushAgree')
    {
        $where = array();
        $where[] = "firebase_instance_id IS NOT NULL";
        switch ($agreeType) {
            case 'CommonPushAgree' :
                $where[] = "common_push_agree IS NOT NULL";
                break;
            case 'MarketingPushAgree' :
                $where[] = "marketing_push_agree IS NOT NULL";
                break;
            default :
                break;
        }

        $where = implode(' AND ', $where);
        $sql = "
            SELECT      firebase_instnace_id
            FROM        (안드로이드유저테이블)
            WHERE       {$where}
        ";
        $data = util::query_to_array($sql);
        $this->message['registration_ids'] = util::array_column($data, 'firebase_instnace_id');
        return $this;
    }

    public function build()
    {
        if (empty($this->message['registration_ids'])) {
            return false;
        }

        # validation

        return $this->message;
    }
}