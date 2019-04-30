var pageController = {
    init: function () {
        this.$form = $('#regiform');

        this.$checkboxes = this.$form.find('input[type="checkbox"]');
        this.$checkboxes.on('change', this.onChangeCheckboxes.bind(this));

        this.$imageSubmit = this.$form.find('#regiformSubmit');
        this.$imageSubmit.on('click', this.onSubmit.bind(this));

        this.$selectCategories = this.$form.find('div.category select');
    },
    onChangeCheckboxes: function (e) {
        if (e.currentTarget.id === 'checkAll') {
            this.$checkboxes.prop('checked', e.currentTarget.checked);
        } else {
            this.$checkboxes[4].checked = (
                this.$checkboxes[0].checked &&
                this.$checkboxes[1].checked &&
                this.$checkboxes[2].checked &&
                this.$checkboxes[3].checked
            );
        }
    },
    onSubmit: function () {
        try {

            // 약관 동의
            for (var i = 0, end = this.$checkboxes.length - 1; i < end; ++i) {
                if (!this.$checkboxes[i].checked) {
                    this.$checkboxes[i].scrollIntoView();
                    throw this.$checkboxes[i].getAttribute('data-name') + '에 동의해주세요';
                }
            }

            // 카테고리 선택 확인
            if (!this.$selectCategories.eq(0).val()) {
                throw '대분류를 선택해주세요';
            }
            if (!this.$selectCategories.eq(1).prop('disabled') &&
                !this.$selectCategories.eq(1).val()) {
                throw '중분류를 선택해주세요';
            }
            if (!this.$selectCategories.eq(2).prop('disabled') &&
                !this.$selectCategories.eq(2).val()) {
                throw '소분류를 선택해주세요';
            }

            this.$form.submit();

        } catch (message) {
            alert(message);
        }
    }
};