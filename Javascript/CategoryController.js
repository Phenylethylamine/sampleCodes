var CategoryController = function (param) {

    // selector 필수
    if (!param.selector) {
        alert('undefined CategoryController.param.selector');
        return;
    }

    // wrapper
    this.$wrap = $(param.selector);
    if (this.$wrap.length === 0) {
        alert('undefined CategoryController.$wrap');
        return;
    }

    // select tags
    this.$select = this.$wrap.find('[data-categoryLevel]');
    if (this.$select.length === 0) {
        alert('undefined CategoryController.$select');
        return;
    }


    // categoryData
    if (!param.categoryData) {
        alert('undefined CategoryController.categoryData');
        return;
    }

    // level에 따른 분류
    this.categoryData = [[], [], []];
    for (var i = 0, end = param.categoryData.length; i < end; ++i) {
        var row = param.categoryData[i];
        var level = this.getThreadLevel(row.thread);
        this.categoryData[level].push(row);
    }

    // 0레벨 옵션 생성
    this.setOptionTags({'level': 0, 'data': this.categoryData[0]});
    this.$select.eq(1).prop('disabled',true);
    this.$select.eq(2).prop('disabled',true);

    // 이벤트 설정
    var that = this;
    this.$select.on('change', function () {
        that.onChange({
            'value': this.value
            , 'changedLevel': Number(this.getAttribute('data-categoryLevel'))
        });
    });

    // 프리셋 적용
    if (param.selected) {
        this.onChange({
            'value': param.selected
            , 'changedLevel': 0
        });
    }
};

CategoryController.prototype.onChange = function (param) {
    var thread = this.splitThread(param.value);
    for (var i = param.changedLevel, end = this.$select.length; i < end; ++i) {
        this.set({'level': i, 'value': thread[i]});
    }
};

CategoryController.prototype.set = function (param) {
    var $select = this.$select.eq(param.level);
    $select.val(param.value);
    var $option = $select.find('option:selected');

    // 하위 레벨 처리
    var nextLevel = param.level + 1;
    $nextSelect = this.$select.eq(nextLevel);
    this.setOptionTags({
        'level': nextLevel
        , 'data': this.getChildrenData({
            'level': nextLevel, 'value': param.value
        })
    });
};

CategoryController.prototype.setOptionTags = function (param) {
    var $select = this.$select.eq(param.level);
    if ($select.length === 0) return;

    if (param.data.length === 0) {
        $select.prop('disabled', true);
    } else {
        $select.prop('disabled', false);
    }

    // 타이틀을 제외하고 삭제
    $select.children(':not(.title)').remove();

    var optionTags = [];
    for (var i = 0, end = param.data.length; i < end; ++i) {
        var row = param.data[i];
        optionTags.push(
            $('<option></option>', {
                'value': row.thread
                , 'data-hasChildren': row.hasChildren
            }).text(row.title)
        );
    }
    $select.append(optionTags);
    $select.children('.title').prop('selected', true);
};

CategoryController.prototype.getChildrenData = function (param) {
    var data = this.categoryData[param.level];
    if (!param.value || data == undefined) return [];
    var result = [];
    for (var i = 0, end = data.length; i < end; ++i) {
        if (data[i].thread.indexOf(param.value) === 0) result.push(data[i]);
    }
    return result;
};

CategoryController.prototype.splitThread = function (threadString) {
    var thread = threadString.split('r');
    for (var i = 0, end = thread.length; i < end; ++i) {
        if (i == 0) continue;
        thread[i] = thread[i - 1] + 'r' + thread[i];
    }
    return thread;
};

CategoryController.prototype.getThreadLevel = function (thread) {
    return (thread.match(/r/g) || []).length;
};

CategoryController.prototype.getValue = function () {
    for (var i = this.$select.length - 1; 0 <= i; --i) {
        var value = this.$select.eq(i).val();
        if (value) return value;
    }
};