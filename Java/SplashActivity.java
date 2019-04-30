package mobile.pricegolf;
import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.content.Context;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.os.AsyncTask;
import android.os.Build;
import android.os.Bundle;
import android.preference.PreferenceManager;
import android.telephony.TelephonyManager;
import android.util.Log;
import android.webkit.WebView;

import com.crashlytics.android.Crashlytics;
import com.google.android.gms.tasks.Task;
import com.google.firebase.iid.FirebaseInstanceId;
import com.google.firebase.iid.InstanceIdResult;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.gun0912.tedpermission.PermissionListener;
import com.gun0912.tedpermission.TedPermission;
import com.koushikdutta.ion.Ion;

import java.lang.ref.WeakReference;
import java.util.List;
import java.util.concurrent.ExecutionException;

import io.fabric.sdk.android.Fabric;
import mobile.pricegolf.dialog.UpdateAppDialog;

// Refactoring 190312YBH
public class SplashActivity extends Activity {

    static String TAG = "SplashActivity";
    public AsyncTask asyncTaskCheckServer;
    public JsonObject joCheckServerResult;
    public SharedPreferences sharedPreferencesSignInfo, sharedPreferencesDefault;
    public Bundle receivedIntentExtras;

    public boolean isForeground = true;
    public boolean skipInitialize = false;
    public boolean isCalledAllTasksComplete = false;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        Log.d("ActivityLifeCycle","SplashActivity.onCreate()");

        // Crash Log
        Fabric.with(this, new Crashlytics());

        receivedIntentExtras = getIntent().getExtras();

        String onClickNotification = null;
        if (receivedIntentExtras != null) onClickNotification = receivedIntentExtras.getString("onClickNotification",null);

        // 메인액티비티가 살아있는 상태에서 NotificationClick으로 실행된 경우 초기화 기능을 Skip합니다
        skipInitialize = (PricegolfApplication.mainActivity != null && onClickNotification != null);
        if (skipInitialize) {
            Log.d(TAG,"skipInitialize");
            allTasksComplete();
            return;
        }

        // 변수 할당
        sharedPreferencesSignInfo = getSharedPreferences("SignInfo",SplashActivity.MODE_PRIVATE);
        sharedPreferencesDefault = PreferenceManager.getDefaultSharedPreferences(this);

        // 권한안내 -> TedPermission -> asyncTaskCheckServer -> allTaskComplete
        if (! TedPermission.isGranted(this,Manifest.permission.READ_PHONE_STATE)) {
            WebView webView = new WebView(this);
            webView.setScrollbarFadingEnabled(false);
            webView.getSettings().setUserAgentString("AndroidWebView");
            webView.clearCache(true);
            webView.loadUrl("(URL)");
            AlertDialog.Builder dialog = new AlertDialog.Builder(this);
            dialog.setView(webView);
            dialog.setPositiveButton("필수권한허용", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface dialogInterface, int i) {
                    dialogInterface.dismiss();
                    SplashActivity.this.startTask();
                }
            });
            dialog.setNegativeButton("App 종료", new DialogInterface.OnClickListener() {
                @Override
                public void onClick(DialogInterface dialogInterface, int i) {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                        SplashActivity.this.finishAndRemoveTask();
                    } else {
                        SplashActivity.this.finish();
                    }
                }
            });
            dialog.setOnCancelListener(new DialogInterface.OnCancelListener() {
                @Override
                public void onCancel(DialogInterface dialog) {
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                        SplashActivity.this.finishAndRemoveTask();
                    } else {
                        SplashActivity.this.finish();
                    }
                }
            });
            dialog.show();
        } else {
            this.startTask();
        }
    }
    private void startTask() {
        TedPermission.Builder tedPermissionBuilder = TedPermission.with(this);
        tedPermissionBuilder.setPermissionListener(
            new PermissionListener() {
                @Override
                public void onPermissionGranted() {
                    Log.d(TAG, "onPermissionGranted");
                    asyncTaskCheckServer = new AsyncTaskCheckServer(SplashActivity.this).executeOnExecutor(AsyncTask.THREAD_POOL_EXECUTOR);
                }
                @Override
                public void onPermissionDenied(List<String> deniedPermissions) {
                    Log.d(TAG, "onPermissionDenied");
                    // 권한 미동의시 App 종료
                    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                        SplashActivity.this.finishAndRemoveTask();
                    } else {
                        SplashActivity.this.finish();
                    }
                }
            });
        tedPermissionBuilder.setDeniedMessage("권한요청이 거부되어 App이 종료됩니다\n[설정] > [권한] 메뉴에서 App 권한을 허용해주세요");
        tedPermissionBuilder.setPermissions(
            // for getDeviceId, getImei, getLine1Number
            Manifest.permission.READ_PHONE_STATE
        );
        tedPermissionBuilder.check();
    }

    // 서버로부터 실행상태를 받아오는 비동기작업객체, 완료 후 checkComplete를 실행한다
    private static class AsyncTaskCheckServer extends AsyncTask<Void,Void,JsonObject> {
        // Activity에 대한 약한 참조 : 메모리 누수 방지
        private WeakReference<SplashActivity> weakReferenceActivity;
        AsyncTaskCheckServer(SplashActivity context) {
            weakReferenceActivity = new WeakReference<>(context);
        }
        @SuppressLint("MissingPermission")
        protected JsonObject doInBackground(Void... params) {
            Log.d(TAG,"AsyncTaskCheckServer");
            SplashActivity splashActivity;
            SharedPreferences sharedPreferencesSignInfo,sharedPreferencesDefault;
            TelephonyManager telephonyManager;
            JsonObject joReturn;
            try {
                splashActivity = weakReferenceActivity.get();
                telephonyManager = (TelephonyManager) splashActivity.getSystemService(Context.TELEPHONY_SERVICE);
                sharedPreferencesSignInfo = splashActivity.sharedPreferencesSignInfo;
                sharedPreferencesDefault = splashActivity.sharedPreferencesDefault;

                // 서버로 보낼 데이터
                String androidUserNumber, userId, line1Number, commonPushAgree, marketingPushAgree,
                    osVersion, appVersion, firebaseInstanceId, imei, deviceId;

                androidUserNumber = String.valueOf(sharedPreferencesSignInfo.getInt("androidUserNumber",0));
                userId = sharedPreferencesSignInfo.getString("user_id","");
                line1Number = telephonyManager.getLine1Number();
                commonPushAgree = sharedPreferencesDefault.getBoolean("commonPushAgree",true) ? "1" : "0";
                marketingPushAgree = sharedPreferencesDefault.getBoolean("marketingPushAgree",true) ? "1" : "0";
                osVersion = String.valueOf(android.os.Build.VERSION.SDK_INT);
                if (android.os.Build.VERSION.SDK_INT >= 28) {
                    appVersion = String.valueOf(
                        splashActivity
                            .getApplicationContext()
                            .getPackageManager()
                            .getPackageInfo(splashActivity.getApplicationContext().getPackageName(),0)
                            .getLongVersionCode()
                    );
                } else {
                    appVersion = String.valueOf(
                        splashActivity
                            .getApplicationContext()
                            .getPackageManager()
                            .getPackageInfo(splashActivity.getApplicationContext().getPackageName(),0).versionCode
                    );
                }
                // TODO onTokenRefresh 추가 필요 190327YBH
                Task<InstanceIdResult> taskForGetToken = FirebaseInstanceId.getInstance().getInstanceId();
                long start = System.currentTimeMillis();
                while(true) {
                    if (taskForGetToken.isComplete()) {
                        firebaseInstanceId = taskForGetToken.isSuccessful() ? taskForGetToken.getResult().getToken() : "";
                        break;
                    } else if ((System.currentTimeMillis() - start) > 3000) {
                        firebaseInstanceId = "";
                        break;
                    }
                }

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O)
                    imei = telephonyManager.getImei();
                else
                    imei = "";

                deviceId = telephonyManager.getDeviceId();

                // 서버로 전송
                String stringJson = Ion.with(splashActivity).load("(URL)")
                    .setBodyParameter("androidUserNumber", androidUserNumber)
                    .setBodyParameter("userId", userId)
                    .setBodyParameter("line1Number", line1Number)
                    .setBodyParameter("commonPushAgree", commonPushAgree)
                    .setBodyParameter("marketingPushAgree", marketingPushAgree)
                    .setBodyParameter("osVersion", osVersion)
                    .setBodyParameter("appVersion", appVersion)
                    .setBodyParameter("firebaseInstanceId", firebaseInstanceId)
                    .setBodyParameter("imei", imei)
                    .setBodyParameter("deviceId", deviceId)
                    .asString()
                    .get();
                Log.d(TAG,"AsyncTaskCheckServer.stringJson : " + stringJson);

                // return
                joReturn = new JsonParser().parse(stringJson).getAsJsonObject();

                // UPDATE sharedPreferencesSignInfo
                if (joReturn.get("result").getAsBoolean()) {
                    SharedPreferences.Editor sharedPreferencesSignInfoEditor = sharedPreferencesSignInfo.edit();
                    int joReturnAdnroidUserNumber = joReturn.get("androidUserNumber").getAsInt();
                    if (Integer.parseInt(androidUserNumber) != joReturnAdnroidUserNumber) {
                        sharedPreferencesSignInfoEditor.putInt("androidUserNumber",joReturnAdnroidUserNumber);
                    }
                    sharedPreferencesSignInfoEditor.apply();
                }
            } catch (ExecutionException e) {
                e.printStackTrace();
                joReturn = new JsonObject();
                joReturn.addProperty("result",false);
                joReturn.addProperty("errorMessage",e.getMessage());
            } catch (InterruptedException e) {
                e.printStackTrace();
                joReturn = new JsonObject();
                joReturn.addProperty("result",false);
                joReturn.addProperty("errorMessage",e.getMessage());
            } catch (PackageManager.NameNotFoundException e) {
                e.printStackTrace();
                joReturn = new JsonObject();
                joReturn.addProperty("result",false);
                joReturn.addProperty("errorMessage",e.getMessage());
            }
            return joReturn;
        }
        protected void onPostExecute(JsonObject joReturn) {
            super.onPostExecute(joReturn);
            joReturn.addProperty("taskName","AsyncTaskCheckServer");
            SplashActivity splashActivity = weakReferenceActivity.get();
            if (splashActivity != null) splashActivity.checkComplete(joReturn);
        }
    }
    // 각 비동기작업객체가 완료되었을때 이 함수를 실행합니다
    // MainActivity로 넘어갈 수 있는 조건을 확인합니다
    public void checkComplete(JsonObject jsonObject) {
        Log.d(TAG,"SplashActivity.checkComplete()");
        Log.d(TAG, "checkComplete : " + jsonObject.toString());
        String taskName = jsonObject.get("taskName").getAsString();
        try {
            switch (taskName) {
                case "AsyncTaskCheckServer" :
                    /*
                     * result 가 false 일 경우 : 서버오류
                     * needToUpdate 가 true 일 경우 : 업데이트 알림
                     */
                    joCheckServerResult = jsonObject;
                    if (! jsonObject.get("result").getAsBoolean()) {
                        throw new Exception(jsonObject.get("message").getAsString());
                    }
                    if (jsonObject.get("needToUpdate").getAsBoolean()) {
                        throw new Exception("needToUpdate");
                    }
                    allTasksComplete();
                    break;
                case "onCancelUpdateAppDialog" :
                    allTasksComplete();
                    break;
            }
        } catch (Exception e) {
            String errorMessage = e.getMessage();
            switch (errorMessage) {
                case "needToUpdate" :
                    /*
                     * 업데이트 다이얼로그
                     * actionUpdate() : 마켓으로 이동합니다
                     * actionCancel() : AsyncTaskCheckUpdate 성공 JSON을 작성하여 checkComplete를 재실행합니다
                     */
                    new UpdateAppDialog(SplashActivity.this, R.layout.dialog_finishapp).show();
                    break;
                default :
                    // 알림 생성
                    new AlertDialog.Builder(this)
                        .setTitle("알림")
                        .setMessage(errorMessage)
                        .setPositiveButton("확인", new DialogInterface.OnClickListener() {
                            public void onClick(DialogInterface dialog, int id) {
                                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                                    SplashActivity.this.finishAndRemoveTask();
                                } else {
                                    SplashActivity.this.finish();
                                }
                            }
                        }).show();
            }
        }
    }
    // 모든 비동기작업객체가 정상완료 되었을때 실행
    public void allTasksComplete() {
        // 조건 검사
        try {
            // 중복실행 방지
            if (isCalledAllTasksComplete) throw new Exception("0");
            // Skip 조건
            if (skipInitialize) throw new Exception("1");
            // MainActivity 로 넘어가기 위한 기본 조건
            if (! this.isForeground) throw new Exception("0");
            if (joCheckServerResult==null) throw new Exception("0");
            if (! joCheckServerResult.get("result").getAsBoolean()) throw new Exception("0");
        } catch (Exception e) {
            switch (e.getMessage()) {
                case "0" : Log.d(TAG, "allTasksComplete : false"); return;
                case "1" : Log.d(TAG, "allTasksComplete : true"); break;
            }
        }

        // 중복실행 방지
        isCalledAllTasksComplete = true;

        Log.d(TAG,"allTasksComplete");

        // MainActivity 실행
        Intent intentForSend = new Intent(SplashActivity.this, MainActivity.class);

        // 공지유무
        if (!skipInitialize && joCheckServerResult.get("isNoticeExists").getAsBoolean()) {
            intentForSend.putExtra("first_execute", "1");
            intentForSend.putExtra("noti_url", joCheckServerResult.get("noticeUrl").getAsString());
            intentForSend.putExtra("noti_name", joCheckServerResult.get("noticeName").getAsString());
        }

        // Noti를 클릭하여 실행되었을때 Extra를 MainActivity로 전달합니다
        if (receivedIntentExtras != null) {
            for (String key : receivedIntentExtras.keySet()) {
                intentForSend.putExtra(key,receivedIntentExtras.getString(key));
            }
        }

        // Notification Channel을 생성합니다
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            getSystemService(NotificationManager.class).createNotificationChannel(
                new NotificationChannel(
                    getString(R.string.default_notification_channel_id), "기본알림", NotificationManager.IMPORTANCE_DEFAULT
                )
            );
            getSystemService(NotificationManager.class).createNotificationChannel(
                new NotificationChannel(
                    getString(R.string.silent_notification_channel_id), "무음알림", NotificationManager.IMPORTANCE_LOW
                )
            );
        }

        if (skipInitialize) {
            intentForSend.putExtra("notificationAssistanceMode",true);
        } else {
            intentForSend.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        }

        SplashActivity.this.startActivity(intentForSend);
        finish();
    }
    @Override
    protected void onResume() {
        super.onResume();
        Log.d("ActivityLifeCycle", "SplashActivity.onStart()");
        this.isForeground = true;
        this.allTasksComplete();
    }

    @Override
    protected void onPause() {
        super.onPause();
        Log.d("ActivityLifeCycle", "SplashActivity.onStop()");
        this.isForeground = false;
    }
}
