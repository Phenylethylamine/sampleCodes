package mobile.pricegolf;

import android.app.PendingIntent;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.preference.PreferenceManager;
import android.support.v4.app.NotificationCompat;
import android.support.v4.app.NotificationCompat.BigPictureStyle;
import android.support.v4.app.NotificationCompat.BigTextStyle;
import android.support.v4.app.NotificationManagerCompat;
import android.util.Log;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;
import com.koushikdutta.ion.Ion;
import com.koushikdutta.ion.builder.Builders;
import com.squareup.picasso.Picasso;

import org.jsoup.helper.StringUtil;

import java.io.IOException;
import java.util.Calendar;
import java.util.Map;
import java.util.concurrent.ExecutionException;

import mobile.pricegolf.connector.LoginManager;

public class MyFirebaseMessagingService extends FirebaseMessagingService {

    static final String TAG = "MyFMS";
    private RemoteMessage remoteMessage;
    private Map<String, String> mapMessageData;

    @Override
    public void onNewToken(String token) {
        Log.d(TAG, "Token : "+token);
    }
    @Override
    public void onMessageReceived(RemoteMessage r) {

        Log.d(TAG,"onMessageReceived");

        this.remoteMessage = r;
        this.mapMessageData = remoteMessage.getData();

        // Log
        if (this.mapMessageData.size() > 0) {
            for (Map.Entry<String,String> entry : this.mapMessageData.entrySet()) {
                Log.d(TAG,"[messageData] " + entry.getKey() + " : " + entry.getValue());
            }
        }

        // data validation
        String messageStructure = this.mapMessageData.get("messageStructure");
        String responseOnMessageReceived = this.mapMessageData.get("responseOnMessageReceived");
        if (messageStructure == null) {
            Log.d(TAG, "messageStructure Is Null");
            return;
        }
        if (responseOnMessageReceived == null) {
            Log.d(TAG, "responseOnMessageReceived Is Null");
            return;
        }

        // Notification
        if (messageStructure.equals("firebaseNotification") || messageStructure.equals("dataNotification")) {
            this.makeNotification();
        }

        // response to server
        if (responseOnMessageReceived.equals("1")) {
            this.responseOnMessageReceived();
        }

        // customTask
        if (messageStructure.equals("dataOnly")) {
            this.customTask();
        }
    }
    /**
     * App Foreground/firebaseNotification 에서 remoteMessage.getNotification() 데이터를 받아 직접 Notification 을 생성합니다
     * dataNotification 에서는 data payload 에 포함된 Notification 데이터를 받아 직접 Notification 을 생성합니다
     */
    public void makeNotification() {
        Log.d(TAG,"makeNotification");

        SharedPreferences defaultSharedPreference = PreferenceManager.getDefaultSharedPreferences(this);

        String
            notificationChannelId = null,
            notificationTitle = null,
            notificationText = null,
            notificationIcon = null,
            notificationLargeIcon = null,
            notificationColor= null,
            notificationImage = null,
            notificationId = null;

        String messageStructure = this.mapMessageData.get("messageStructure");
        // 사용케이스 없음
        if (messageStructure.equals("firebaseNotification")) {
            RemoteMessage.Notification remoteNotification = this.remoteMessage.getNotification();
            notificationChannelId = remoteNotification.getChannelId();
            notificationTitle = remoteNotification.getTitle();
            notificationText = remoteNotification.getBody();
            notificationIcon = remoteNotification.getIcon();
        } else if (messageStructure.equals("dataNotification")) {
            // required
            notificationTitle = this.mapMessageData.get("notificationTitle");
            notificationText = this.mapMessageData.get("notificationText");
            // has default value
            notificationChannelId = this.mapMessageData.get("notificationChannelId");
            notificationIcon = this.mapMessageData.get("notificationIcon");
            notificationLargeIcon = this.mapMessageData.get("notificationLargeIcon");
            notificationColor = this.mapMessageData.get("notificationColor");
            notificationImage = this.mapMessageData.get("notificationImage");
            notificationId =  this.mapMessageData.get("notificationId");
        }


        // validation required value
        if (notificationTitle == null) {
            Log.d(TAG, "notificationTitle is null");
            return;
        }
        if (notificationText == null) {
            Log.d(TAG, "notificationText is null");
            return;
        }

        // null to default value, cast
        if (notificationChannelId == null) {
            // pushDenyTime
            try {
                // 서버에서 pushDenyTime을 무시하라고 지시한 경우
                if (this.mapMessageData.get("ignorePushDenyTime") != null) throw new Exception();
                // pushDenyTime이 설정되지 않은 경우
                if (! defaultSharedPreference.getBoolean("pushDenyTime", false)) throw new Exception();
                String pushDenyTimeStart = defaultSharedPreference.getString("pushDenyTimeStart", "");
                String pushDenyTimeEnd = defaultSharedPreference.getString("pushDenyTimeEnd", "");
                if (pushDenyTimeStart.equals("") || pushDenyTimeEnd.equals("")) throw new Exception();
                // HH:MM 나누기
                String[] pushDenyTimeStartExploded = pushDenyTimeStart.split(":");
                String[] pushDenyTimeEndExploded = pushDenyTimeEnd.split(":");
                int pushDenyTimeStartMinutes = (Integer.valueOf(pushDenyTimeStartExploded[0]) * 60) + Integer.valueOf(pushDenyTimeStartExploded[1]);
                int pushDenyTimeEndMinutes = (Integer.valueOf(pushDenyTimeEndExploded[0]) * 60) + Integer.valueOf(pushDenyTimeEndExploded[1]);
                // 시간이 동일한 경우 설정오류로 간주
                if (pushDenyTimeStartMinutes == pushDenyTimeEndMinutes) throw new Exception();
                // 현재시간
                Calendar calendar = Calendar.getInstance();
                int currentMinutes = (calendar.get(Calendar.HOUR_OF_DAY)*60) + calendar.get(Calendar.MINUTE);
                // 시작시간이 큰 경우 (23:00 ~ 08:00)
                if (pushDenyTimeStartMinutes > pushDenyTimeEndMinutes) {
                    if (pushDenyTimeStartMinutes < currentMinutes || currentMinutes < pushDenyTimeEndMinutes) {
                        notificationChannelId = getString(R.string.silent_notification_channel_id);
                    }
                // 종료시간이 큰 경우 (00:00 ~ 09:00)
                } else if (pushDenyTimeStartMinutes < currentMinutes && currentMinutes < pushDenyTimeEndMinutes) {
                        notificationChannelId = getString(R.string.silent_notification_channel_id);
                } else {
                    notificationChannelId = getString(R.string.default_notification_channel_id);
                }
            } catch(Exception ignored) {
                notificationChannelId = getString(R.string.default_notification_channel_id);
            }

            // 위 조건에서 기본알림 상태이고, 종류별 무음 알림을 설정한 경우
            if (notificationChannelId.equals(getString(R.string.default_notification_channel_id))) {
                String onClickNotification = this.mapMessageData.get("onClickNotification");
                switch (onClickNotification) {
                    case "MessageActivity" :
                        if (defaultSharedPreference.getBoolean("silentNotificationForMessage", false))
                            notificationChannelId = getString(R.string.silent_notification_channel_id);
                        break;
                    case "MypageActivity" :
                        if (defaultSharedPreference.getBoolean("silentNotificationForMypage", false))
                            notificationChannelId = getString(R.string.silent_notification_channel_id);
                        break;
                    case "QnaActivity" :
                        if (defaultSharedPreference.getBoolean("silentNotificationForQna", false))
                            notificationChannelId = getString(R.string.silent_notification_channel_id);
                        break;
                }
            }
        }


        if (notificationIcon == null) notificationIcon = "ic_stat_ic_mainicon_noti_2";
        int resourceNotificationIcon = getResources().getIdentifier(notificationIcon,"drawable",getPackageName());

        if (notificationLargeIcon == null) notificationLargeIcon = "ic_pricegolf_noti";
        int resourceNotificationLargeIcon = getResources().getIdentifier(notificationLargeIcon,"drawable",getPackageName());

        int resourceNotificationColor;
        if (notificationColor == null) {
            resourceNotificationColor = getResources().getColor(R.color.Pricegolf_New);
        } else {
            resourceNotificationColor = Color.parseColor(notificationColor);
        }

        int IntNotificationId;
        if (notificationId == null) {
            IntNotificationId = 0;
        } else {
            IntNotificationId = Integer.parseInt(notificationId);
        }

        // style
        BigPictureStyle bigPictureStyle = null;
        BigTextStyle bigTextStyle = null;
        if (notificationImage != null) {
            try {
                Bitmap bitmap = Picasso.get().load(notificationImage).get();
                bigPictureStyle = new BigPictureStyle().bigPicture(bitmap);
            } catch (IOException e) {
                e.printStackTrace();
            }
        } else {
            bigTextStyle = new BigTextStyle().bigText(notificationText);
        }

        // Create Intent for OnClickNotification
        Intent intent = new Intent(this, SplashActivity.class);
        if (this.mapMessageData.size() > 0) {
            for (Map.Entry<String,String> entry : this.mapMessageData.entrySet()) {
                intent.putExtra(entry.getKey(),entry.getValue());
            }
        }
        PendingIntent pendingIntent = PendingIntent.getActivity(this, 0, intent, PendingIntent.FLAG_UPDATE_CURRENT);

        // Create Notification
        NotificationCompat.Builder notificationBuilder = new NotificationCompat.Builder(this,notificationChannelId)
            .setContentIntent(pendingIntent)
            .setContentTitle(notificationTitle)
            .setContentText(notificationText)
            .setColor(resourceNotificationColor)
            .setSmallIcon(resourceNotificationIcon)
            .setLargeIcon(BitmapFactory.decodeResource(getResources(), resourceNotificationLargeIcon))
            .setStyle(bigPictureStyle!=null ? bigPictureStyle : bigTextStyle)
            .setPriority(NotificationCompat.PRIORITY_DEFAULT)
            .setAutoCancel(true)
            .setCategory(NotificationCompat.CATEGORY_MESSAGE);

        NotificationManagerCompat
            .from(this)
            .notify(IntNotificationId, notificationBuilder.build());
    }

    public void responseOnMessageReceived() {
        Log.d(TAG,"responseOnMessageReceived");
        Builders.Any.B ionBuilder = Ion.with(this)
            .load("POST", getResources().getString(R.string.APP_SERVER_URL));
        for (Map.Entry<String,String> entry : this.mapMessageData.entrySet()) {
            ionBuilder.setBodyParameter(entry.getKey(),entry.getValue());
        }
        ionBuilder.setBodyParameter("mode","responseOnMessageReceived");
        ionBuilder.setBodyParameter("androidUserNumber",
            String.valueOf(getSharedPreferences("SignInfo",SplashActivity.MODE_PRIVATE).getInt("androidUserNumber",0))
        );
        try {
            ionBuilder.asString().get();
        } catch (ExecutionException e) {
            e.printStackTrace();
        } catch (InterruptedException e) {
            e.printStackTrace();
        }
    }
    public void customTask() {
        Log.d(TAG,"customTask");

        String onClickNotification = this.mapMessageData.get("onClickNotification");
        switch (onClickNotification) {
            case "LogoutTask" :
                new LoginManager(this).execute("logout");
                break;
        }
    }
}