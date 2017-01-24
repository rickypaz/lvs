scroll_active = true;
function empty_field_and_submit() {
    var cf   = document.getElementById('sendform');
    var inpf = document.getElementById('inputform');
    cf.chatlv_msgidnr.value = parseInt(cf.chatlv_msgidnr.value) + 1;
    cf.chatlv_message.value = inpf.chatlv_message.value;
    inpf.chatlv_message.value = '';
    cf.submit();
    inpf.chatlv_message.focus();
    return false;
}
function setfocus() {
    document.getElementsByName("chatlv_message")[0].focus();
}
