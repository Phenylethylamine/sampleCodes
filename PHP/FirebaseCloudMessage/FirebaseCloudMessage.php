<?php

/**
 * Class FirebaseCloudMessageService
 * Reference : https://firebase.google.com/docs/cloud-messaging/http-server-ref
 */
class FirebaseCloudMessage
{

    const TAG = 'FirebaseCloudMessage';

    /**
     * @param $message
     */
    static function send($message)
    {
        if (count($message['registration_ids']) === 0) return;

        $message['data']['messageNumber'] = self::insertRequestMessageLog($message);

        static $HEADER = array(
            'Content-Type:application/json',
            'Authorization:key=***'
        );
        static $url = 'https://fcm.googleapis.com/fcm/send';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $HEADER);
        curl_setopt($ch, CURLOPT_POSTFIELDS, util::array_to_json($message));
        $response = curl_exec($ch);
        curl_close($ch);

        $sql = "
            UPDATE (FCM로그테이블) SET
                request_result = '" . addslashes($response) . "'
            WHERE number = {$message['data']['messageNumber']}
        ";
        query($sql);

        if ($GLOBALS['print_process']) {
            echoDev(
                self::TAG . "::send()",
                '[REQUEST]', $HEADER, $message, util::array_to_json($message),
                '[RESPONSE]', $response, util::json_to_array($response)
            );
        }
    }

    static function insertRequestMessageLog($message)
    {
        $requestLog = array();
        $requestLog['send_date'] = $GLOBALS['YMDHIS'];
        $requestLog['registration_ids_length'] = count($message['registration_ids']);
        $requestLog['message_structure'] = $message['data']['messageStructure'];
        $requestLog['on_click_notification'] = $message['data']['onClickNotification'];
        $requestLog['response_on_message_received'] = $message['data']['responseOnMessageReceived'];
        $requestLog['response_on_click_notification'] = $message['data']['responseOnClickNotification'];
        $requestLog['request_content'] = util::array_to_json($message);
        util::insert_array('(FCM로그테이블)', $requestLog);
        return mysql_insert_id();
    }

    static function insertResponseMessageLog($data)
    {
        $responseLog = array();
        $responseLog['message_number'] = $data['messageNumber'];
        $responseLog['android_user_number'] = $data['androidUserNumber'];
        $responseLog['response_type'] = $data['mode'];
        $responseLog['response_message'] = util::array_to_json($data);
        $responseLog['response_date'] = $GLOBALS['YMDHIS'];
        return util::insert_array('(FCM로그테이블)', $responseLog);
    }
}