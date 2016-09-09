
function ajax_unbind_course(blockid, catid, courseid, wwwroot) {
    url = wwwroot.'/blocks/auditsquiz_results/services/service.php?what=unbind&blockid='+blockid+'&qcatid='+catid+'&courseid='+courseid;
    
    $.get(url, function(data) {
        // Hide course block
        $('#coursebinding'+catid+'_'+courseid).css('display', 'none');
    });
}