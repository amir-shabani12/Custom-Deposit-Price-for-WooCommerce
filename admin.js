jQuery(function($){
    // فعال‌سازی select2 برای انتخاب چندگانه
    if ( $.fn.select2 ) {
        $('.wc-enhanced-select').select2({
            placeholder: 'انتخاب کنید...',
            allowClear: true
        });
    }
});
