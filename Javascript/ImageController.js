/*


상품 이미지 업로드 및 순서 정렬 프로그램
160811 YBH
리스트의 갯수와 디자인은 자유롭게 변경 가능합니다.




(EXAMPLE)


<style>
#sortBox{position:relative; padding:10px 0 0 10px;}
#sortBox::after{content:''; display:block; clear:both;}
#sortBox li{display:block; float:left; width:148px; height:148px; margin:0 10px 10px 0; border:1px solid #000; box-sizing:border-box; z-index:3;}
#sortBox li div{position:absolute; background-position:center; background-size:contain; background-repeat:no-repeat; cursor:pointer; z-index:2;}
#sortBox li div:hover{box-shadow:0px 0px 2px #f00;}
#sortBox li div.dragItem{box-shadow:0 0 5px #f00; z-index:1;}
#sortBox li.itemHover{background:#ccc;}
</style>


<ul id="imgCtrl">
	<li></li>
	<li></li>
	<li></li>
	<li></li>
	<li></li>
	<li></li>
	<li></li>
	<li></li>
	<li></li>
	<li></li>
</ul>


<script>
$(function(){
	new ImageController( {imageList:Array, selector:selector} );
});
</script>


*/

var ImageController = function (arg) {

    // 드래그 업로드 지원여부
    if (-1 < navigator.userAgent.indexOf('MSIE 8.0')) {
        alert('Internet Explorer 8버전 이하에서는 상품을 등록할 수 없습니다\nInternet Explorer를 최신버전으로 업데이트하거나,\nChrome 등의 브라우저를 이용해주시기 바랍니다.');
    } else if (0 < navigator.userAgent.indexOf('MSIE 9.0'))
        this.dragUploadable = false;
    else
        this.dragUploadable = true;

    // 이미지 목록
    this.image = arg.imageList;
    // 셀렉터, 래퍼
    this.body = $('body');
    this.selector = arg.selector;
    this.wrapper = $(arg.selector);
    // 이미지 배열을 토대로 초기화
    this.init();
    // 이벤트 리스너 등록
    this.addEvent();

};

// 초기화 : 이미지 순서변경시, 이미지 추가/제거시 실행
ImageController.prototype.init = function () {

    // 순서 정렬 박스
    this.sortBox = this.wrapper.children('ul');

    // iframe
    if (!this.frame) {
        this.frame = $('<iframe>').attr({"name": "ImageController", "id": "ImageController"}).css('display', 'none');
        this.body.append(this.frame);
    }
    // form
    if (!this.form) {
        this.form = $('<form>').attr({
            "action": "/ajax_image_upload.php"
            , "method": "post"
            , "encoding": "multipart/form-data"
            , "enctype": "multipart/form-data"
            , "target": "ImageController"
        }).css('display', 'none');
        // input
        // png 임시 제거
        //this.input = $('<input type="file" name="uploadedFile" id="imageUploader" accept=".jpg, .jpeg, .png, .gif">');
        this.input = $('<input type="file" name="uploadedFile" id="imageUploader" accept=".jpg, .jpeg, .gif">');
        this.body.append(this.form.append(this.input));
    }

    // uploadLayer
    if (!this.uploadLayer && this.dragUploadable) {
        this.uploadLayer =
            $('<div>').css({
                'display': 'none'
                , 'position': 'absolute'
                , 'width': '100%'
                , 'height': '100%'
                , 'top': '0', 'left': '0', 'right': '0', 'bottom': '0'
                , 'background': 'hsla(210,50%,20%,0.75)'
            }).append(
                $('<div>')
                    .css({
                        'position': 'absolute'
                        , 'width': '100%'
                        , 'top': '50%'
                        , 'margin-top': '-8%'
                        , 'text-align': 'center'
                        , 'color': 'white'
                        , 'font-size': '30px'
                        , 'font-weight': 'bold'
                    })
                    .append([
                        '<i class="fa fa-upload" style="font-size:100px"></i><br>'
                        , '<p>파일을 업로드하려면 이곳에 내려놓아주세요</p>'
                    ])
            );
        this.wrapper.append(this.uploadLayer);
    }

    // 리스트 설정
    this.list = this.sortBox.children();
    this.listSize = this.list.width();

    // 아이템 위치 조정을 위해 border-width 저장
    var listBorderWidth = parseInt(this.list.css('border-left-width'));

    // 각 리스트에 div(이미지컨테이너) 할당
    for (var i = 0; i < this.list.length; i++) {
        this.list[i].containOffsetLeft = this.list[i].offsetLeft + listBorderWidth;
        this.list[i].containOffsetTop = this.list[i].offsetTop + listBorderWidth;
        // 각 li별로 div 생성
        var divStyle = {
            left: this.list[i].containOffsetLeft
            , top: this.list[i].containOffsetTop
            , width: this.list[i].clientWidth
            , height: this.list[i].clientHeight
        };
        if (this.image[i]) {
            divStyle['background-image'] = 'url("' + this.image[i] + '")';
            var div = $('<div class="item">')
                .css(divStyle)
                .append('<button class="delete"><i class="fa fa-times delete"></i></button>');
        } else {

            if (this.dragUploadable)
                var infoText = '<i class="fa fa-plus"></i>새 이미지를 등록하려면<br>이곳을 클릭하거나<br>이미지를 끌어다 놓으세요';
            else
                var infoText = '<i class="fa fa-plus"></i>새 이미지를 등록하려면<br>이곳을 클릭하세요';

            var div = $('<div class="addItem">').css(divStyle).html(
                $('<label for="imageUploader">').html(infoText)
            );
        }
        this.list.eq(i).html(div);
    }

    // 아이템 설정
    this.item = this.list.children();
    for (var i = 0; i < this.item.length; i++) {
        this.item[i].originalIndex = i;
        this.item[i].tempIndex = i;
    }

    // dragItem 준비
    this.dragItem = null;
}

// 이벤트 연결
ImageController.prototype.addEvent = function () {

    // drag item, delete item
    this.sortBox.on('mousedown', '.item', $.proxy(this.dragStart, this));
    // add item by drag&drop
    if (this.dragUploadable) {
        this.body.on('dragover', $.proxy(this.addEffect, this));
        this.uploadLayer.on('click', $.proxy(this.hideUploadLyaer, this));
        this.wrapper.on('drop', $.proxy(this.addItem, this));
    }

    // add item by frame
    this.input.on('change', $.proxy(this.addItemByFrame, this));
    this.frame.on('load', $.proxy(this.addItemResponseByFrame, this));
};


// 이미지 순서변경 이벤트
ImageController.prototype.dragStart = function (e) {

    e.stopPropagation();
    e.preventDefault();

    // 삭제 버튼이 눌렸을 경우
    if ($(e.target).hasClass('delete')) {
        if (confirm('이미지를 삭제하시겠습니까?')) {
            this.image.splice($(e.currentTarget).parents('li').index(), 1);
            this.init();
        }
        return true;
    }


    // 왼쪽 버튼이 아니면 실행 안함
    if (e.buttons != 1) return;
    // 이미 드래그 모드라면 에러발생을 피하기 위해 dragStart를 실행하지 않습니다
    if (this.dragItem) return;
    // 드래그 아이템 준비
    this.dragItem = {item: $(e.currentTarget)};
    this.dragItem.parent = this.dragItem.item.parent();
    this.dragItem.index = this.dragItem.parent.index();
    // 드래그 시작 좌표(이미지 이동용)
    this.dragItem.startOffsetX = this.dragItem.item[0].offsetLeft - 5;
    this.dragItem.startOffsetY = this.dragItem.item[0].offsetTop - 5;
    // 드래그 아이템 절반 넓이(이미지 호버 위치 계산용)
    this.dragItem.halfSize = this.dragItem.item[0].offsetWidth / 2;
    // 드래그 시작 효과(아이템)
    this.dragItem.item.css({
        'left': this.dragItem.startOffsetX
        , 'top': this.dragItem.startOffsetY
    }).addClass('dragItem');

    // 드래그 시작 지점(마우스)
    this.dragStartX = e.clientX;
    this.dragStartY = e.clientY;

    // 클릭후 바로 놓을 경우를 대비해, 드래그무브용 변수 설정
    this.hoverIndex = this.dragItem.index;

    // 마우스 이동 이벤트 추가
    this.sortBox.on('mousemove.ImageController', $.proxy(this.dragMove, this));
    // 드래그 종료 이벤트 추가
    this.sortBox.on({'mouseup.ImageController': $.proxy(this.dragEnd, this)});

};
// drag 시작시 this.sortBox에 이벤트가 연결됩니다
ImageController.prototype.dragMove = function (e) {

    // 마우스 시작점으로부터 이동 거리 계산
    var moveX = e.clientX - this.dragStartX;
    var moveY = e.clientY - this.dragStartY;

    // 아이템 시작점으로부터 마우스 이동거리만큼 아이템 이동
    this.dragItem.item.css({
        top: this.dragItem.startOffsetY + moveY
        , left: this.dragItem.startOffsetX + moveX
    });

    // 아이템의 위치를 기반으로 호버된 영역을 찾는다
    this.hoverIndex = this.findHoverIndex();
    if (this.hoverIndex === false) {
        this.hoverIndex = this.dragItem.index;
    }

    // 리스트에 마우스오버 효과
    this.list.removeClass('itemHover');
    var hoverTarget = this.list.eq(this.hoverIndex);
    hoverTarget.addClass('itemHover');

    // 이미지 이동 효과
    // 마우스오버된 영역이 드래그아이템의 원래 자리일 경우
    if (this.dragItem.index === this.hoverIndex) {
        for (var i = 0; i < this.item.length; i++) {
            // 드래그 아이템은 제외합니다
            if (this.dragItem.index == i) continue;
            // 드래그아이템을 제외한 모든 아이템을 원래 위치로
            var targetIndex = this.item[i].originalIndex;
            // 애니메이션 실행
            if (this.item[i].tempIndex != targetIndex) {
                this.item.eq(i).stop(true, true).animate({
                    'left': this.list[targetIndex].containOffsetLeft,
                    'top': this.list[targetIndex].containOffsetTop,
                }, 100);
                this.item[i].tempIndex = targetIndex;
            }
        }
    }
    // 앞으로 갈 경우
    else if (this.hoverIndex < this.dragItem.index) {
        for (var i = 0; i < this.item.length; i++) {
            // 드래그 아이템은 제외합니다
            if (this.dragItem.index == i) continue;
            // 마우스오버 위치보다 앞의 아이템들은 원래 위치로
            if (i < this.hoverIndex) {
                var targetIndex = this.item[i].originalIndex;
            }
            // 마우스오버 위치보다 크거나 같고, 드래그아이템보다 작은 아이템들은 한칸씩 뒤로
            else if (i < this.dragItem.index) {
                var targetIndex = this.item[i].originalIndex + 1;
            }
            // 나머지는 원래 위치로
            else {
                var targetIndex = this.item[i].originalIndex;
            }
            // 애니메이션 실행
            if (this.item[i].tempIndex != targetIndex) {
                this.item.eq(i).stop(true, true).animate({
                    'left': this.list[targetIndex].containOffsetLeft,
                    'top': this.list[targetIndex].containOffsetTop,
                }, 100);
                this.item[i].tempIndex = targetIndex;
            }
        }
    }
    // 뒤로 갈 경우
    else {
        for (var i = 0; i < this.item.length; i++) {
            // 드래그 아이템은 제외합니다
            if (this.dragItem.index == i) continue;
            // 드래그아이템보다 앞의 아이템들은 원래 위치로
            if (i < this.dragItem.index) {
                var targetIndex = this.item[i].originalIndex;
            }
            // 드래그아이템보다 크고, 마우스오버 위치보다 작거나 같은 아이템들은 한칸씩 앞으로
            else if (i <= this.hoverIndex) {
                var targetIndex = this.item[i].originalIndex - 1;
            }
            // 나머지는 원래 위치로
            else {
                var targetIndex = this.item[i].originalIndex;
            }
            // 애니메이션 실행
            if (this.item[i].tempIndex != targetIndex) {
                this.item.eq(i).stop(true, true).animate({
                    'left': this.list[targetIndex].containOffsetLeft,
                    'top': this.list[targetIndex].containOffsetTop,
                }, 100);
                this.item[i].tempIndex = targetIndex;
            }
        }
    }

};
// 드래그 이동시 드래그 아이템의 위치를 기반으로 호버된 영역의 INDEX를 반환합니다
ImageController.prototype.findHoverIndex = function () {
    // 드래그 아이템의 중앙 좌표
    var dragItemCenterX = this.dragItem.item[0].offsetLeft + this.dragItem.halfSize;
    var dragItemCenterY = this.dragItem.item[0].offsetTop + this.dragItem.halfSize;

    // 리스트 좌표를 기준으로 확인
    for (var i = 0; i < this.list.length; i++) {
        var topEdge = this.list[i].offsetTop;
        var bottomEdge = this.list[i].offsetTop + this.listSize - 1;
        var leftEdge = this.list[i].offsetLeft;
        var rightEdge = this.list[i].offsetLeft + this.listSize - 1;

        if (dragItemCenterY < topEdge) {
            return false;
        } else if (dragItemCenterY > bottomEdge) {
            i = i + 4;
            continue;
        } else if (dragItemCenterX > leftEdge && dragItemCenterX < rightEdge) {
            return (this.image.length <= i) ? this.image.length - 1 : i;
        }
    }
    return false;
}
// 드래그 종료시 drop 위치에 따라 이미지 순서를 변경합니다(배열 변경 후 표현 초기화)
ImageController.prototype.dragEnd = function (e) {
    e.stopPropagation();
    e.preventDefault();

    // 이벤트 제거
    $(this.sortBox).off('.ImageController');

    // 드래그 아이템 셋팅
    // 제자리거나 다른 박스 위에 두지않으면 원상복구
    if (this.dragItem.index === this.hoverIndex || this.hoverIndex === false) {
        this.dragItem.item.animate({
            'left': this.list[this.dragItem.index].containOffsetLeft
            , 'top': this.list[this.dragItem.index].containOffsetTop
        }, 50, $.proxy(function () {
            // 클래스 제거
            this.dragItem.item.removeClass('dragItem');
            this.list.removeClass('itemHover');
            // 드래그아이템 초기화
            this.dragItem = null;
        }, this));
    }
    // 다른 박스로 이동시
    else {
        this.dragItem.item.animate({
            'left': this.list[this.hoverIndex].containOffsetLeft
            , 'top': this.list[this.hoverIndex].containOffsetTop
        }, 50, $.proxy(function () {
            // 클래스 제거
            this.dragItem.item.removeClass('dragItem');
            this.list.removeClass('itemHover');
            // 드래그아이템의 tempIndex 변경
            this.item[this.dragItem.index].tempIndex = this.hoverIndex;
            for (var i = 0, newImageArray = []; i < this.item.length; i++) {
                // 각 아이템들의 tempIndex에 맞게 이미지 배열 변경
                if (this.image[this.item[i].originalIndex]) newImageArray[this.item[i].tempIndex] = this.image[this.item[i].originalIndex];
            }
            // 객체의 이미지 순서 정보에 저장하고 초기화
            this.image = newImageArray;
            this.init();
        }, this));
    }
};


// 이미지 업로드
// 로컬 이미지를 드래그오버 할 경우 효과
ImageController.prototype.addEffect = function (e) {
    e.preventDefault();
    // wrapper 안에서 드래그 오버중인지 확인
    var dragOverWrapper = $(e.target).parents(this.selector).length;
    // wrapper 안에 있을때
    if (dragOverWrapper) {
        if (this.dragOverWrapper) return;
        this.dragOverWrapper = dragOverWrapper;
        this.uploadLayer.show();
    } else {
        if (!this.dragOverWrapper) return;
        this.dragOverWrapper = dragOverWrapper;
        this.uploadLayer.hide();
    }
}
ImageController.prototype.hideUploadLyaer = function (e) {

    this.dragOverWrapper = false;
    this.uploadLayer.hide();

}


ImageController.prototype.addItem = function (e) {
    try {

        nowLoading();
        e.preventDefault();
        this.hideUploadLyaer();

        this.addItemProcess = {
            files: e.originalEvent.target.files || e.originalEvent.dataTransfer.files
            , currentIndex: 0, response: []
        };

        // 드래그된 파일이 없으면 취소
        if (!this.addItemProcess.files.length)
            throw '컴퓨터에 저장된 이미지만 업로드할 수 있습니다';

        // 추가되었을때 합이 10이 넘으면 알림 후 취소
        if (this.addItemProcess.files.length + this.image.length > 10)
            throw '이미지는 10개까지 등록할 수 있습니다';

        // 이미지 등록 시작
        this.addItemRequest();

    } catch (e) {
        alert(e);
        nowLoadingEnd();
        return false;
    }
};


ImageController.prototype.addItemRequest = function () {

    var FD = new FormData;
    FD.append('uploadedFile', this.addItemProcess.files[this.addItemProcess.currentIndex]);
    FD.append('imageIndex', this.image.length + this.addItemProcess.currentIndex);
    $.ajax({
        url: '/ajax_image_upload.php'
        , data: FD
        , processData: false
        , contentType: false
        , method: 'POST'
        , success: $.proxy(this.addItemResponse, this)
    });

}
ImageController.prototype.addItemResponse = function (response) {

    this.addItemProcess.response[this.addItemProcess.currentIndex] = {};
    try {

        var json = JSON.parse(response);
        // (boolean)result, (string)url, (string)errorMessage

        // result 저장
        this.addItemProcess.response[this.addItemProcess.currentIndex] = json;

    }
        // json 형태의 반환값이 아닐때(PHP에러,CGI에러)
    catch (e) {

        this.addItemProcess.response[this.addItemProcess.currentIndex].result = false;
        this.addItemProcess.response[this.addItemProcess.currentIndex].responseText = response;

        // 에러 내용을 기록합니다
        $.post('/ajax_image_upload.php', {mode: 'errorLog', data: response});

    }
    // 해당 아이템 완료 처리
    this.addItemProcess.response[this.addItemProcess.currentIndex].complete = true;

    // 다음 업로드파일이 있으면 업로드 시작
    if (this.addItemProcess.files[this.addItemProcess.currentIndex + 1]) {
        this.addItemProcess.currentIndex++;
        this.addItemRequest();
    }
    // 없으면 완료 처리 시작
    else {
        this.addItemComplete();
    }
};
ImageController.prototype.addItemComplete = function () {

    // 알림 준비
    var alertString = "이미지 업로드 중 에러가 발생하였습니다";

    // 실패기록을 검색
    for (var i in this.addItemProcess.response) {

        // 결과 출력 준비
        var resultText = ' : ';

        // 성공
        if (this.addItemProcess.response[i].result) {

            // 결과 출력 준비
            resultText += '성공';

            // 이미지 배열에 추가
            this.image[this.image.length] = this.addItemProcess.response[i].url;

        }
        // 실패 케이스는 알림 준비
        else {

            // 결과 출력 준비
            var needAlert = true;

            if (this.addItemProcess.response[i].errorMessage)
                resultText += this.addItemProcess.response[i].errorMessage;
            else
                resultText += '알 수 없는 오류';

        }

        // 결과 출력 준비
        alertString += '\n' + this.addItemProcess.files[i].name + resultText;

    }

    // 객체 초기화
    this.init();

    // 실패 케이스가 있으면 알림
    if (needAlert) alert(alertString);

    nowLoadingEnd();

}


ImageController.prototype.addItemByFrame = function (e) {

    // 값이 없으면 return;
    if (!this.input.val()) return;
    nowLoading();
    // submit
    this.form.submit();

};
ImageController.prototype.addItemResponseByFrame = function () {

    try {
        var response = this.frame.contents().text();
        if (response == '') return;
        var json = JSON.parse(response);

        if (json.result) {
            // 이미지 배열에 추가
            this.image[this.image.length] = json.url;
            // 객체 초기화
            this.init();
        } else {
            if (json.errorMessage) alert(json.errorMessage);
            else alert('error : 알 수 없는 오류');
        }
    } catch (e) {
        // 에러 내용을 기록합니다
        $.post('/ajax_image_upload.php', {mode: 'errorLog', data: response});
        alert('error : 다시 시도해주세요');
    }

    nowLoadingEnd();

};


ImageController.prototype.makeInput = function () {

    // wrapper 안에 input이 있는지 확인하고 제거합니다.
    this.wrapper.find('input[type="hidden"]').remove();

    // input을 준비합니다
    var inputs = [];
    for (var i = 0; i < this.image.length; i++) {
        inputs[i] = $('<input>').attr({
            'type': 'hidden'
            , 'name': 'img' + i
            , 'value': this.image[i].split('/').splice(-1, 1)
        });
    }
    this.wrapper.append(inputs);

}