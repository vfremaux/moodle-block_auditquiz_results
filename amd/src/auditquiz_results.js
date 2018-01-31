// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// jshint unused: true, undef:true

define(['jquery', 'core/log', 'html2canvas'], function($, log, html2canvas){

    var auditquiz_results = {

        init: function() {
            $('.auditquiz-results-unbind').bind('click', this.unbind_course);
            $('.auditquiz-snapshot-btn').bind('click', this.send_image);

            log.debug('AMD Auditquiz results initialized');
        },

        unbind_course: function(e) {

            e.preventDefault();

            that = $(this);

            matches = that.attr('id').split('-');
            blockid = matches[1];
            catid = matches[2];
            courseid = matches[3];

            url = M.cfg.wwwroot + '/blocks/auditquiz_results/ajax/service.php';
            url += '?what=unbind';
            url += '&blockid=' + blockid;
            url += '&qcatid=' + catid;
            url += '&courseid=' + courseid;

            $.get(url, function(data) {
                // Hide course block.
                $('#id-coursebinding-' + catid + '-' + courseid).css('display', 'none');
            });
        },

        send_image: function () {

            var that = $(this);

            var matches = that.attr('id').split('-');
            var userid = matches[2];
            var blockid = matches[3];
            var elementid = 'id-auditquiz-' + userid + '-' + blockid;

            html2canvas($('#' + elementid), {
                onrendered: function (canvas) {
                    // $("#previewImage").append(canvas);

                    var imagedata = canvas.toDataURL();

                    var url = M.cfg.wwwroot + '/blocks/auditquiz_results/ajax/service.php';

                    var postdata = '?what=storeimage';
                    postdata += '&imageurl=' + imagedata;
                    postdata += '&blockid=' + blockid;
                    postdata += '&userid=' + userid;

                    $.post(url, postdata, function() {
                        $('#id-snapshot-feedback').html("Snaphot stored");
                    });
                }
            });
        }
    };

    return auditquiz_results;

});