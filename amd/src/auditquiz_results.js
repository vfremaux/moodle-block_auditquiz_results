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

define(['jquery', 'core/log', 'core/config', 'block_auditquiz_results/html2canvas'], function($, log, cfg, html2canvas) {

    var auditquizresults = {

        init: function() {
            $('.auditquiz-results-unbind').bind('click', this.unbind_course);
            $('.auditquiz-snapshot-btn').bind('click', this.send_image);
            $('.auditquiz-snapshots').on('click', '.auditquiz-snapshot-delete-btn', '', this.delete_image);
            $('.user-sort').bind('click', [], this.change_user_sorting);

            log.debug('AMD Auditquiz results initialized');
        },

        unbind_course: function(e) {

            e.preventDefault();

            var that = $(this);

            var matches = that.attr('id').split('-');
            var blockid = matches[1];
            var catid = matches[2];
            var courseid = matches[3];

            var url = cfg.wwwroot + '/blocks/auditquiz_results/ajax/service.php';
            url += '?what=unbind';
            url += '&blockid=' + blockid;
            url += '&qcatid=' + catid;
            url += '&courseid=' + courseid;

            $.get(url, function() {
                // Hide course block.
                $('#id-coursebinding-' + catid + '-' + courseid).css('display', 'none');
            }, 'html');
        },

        send_image: function() {

            var that = $(this);
            var handleid = that.attr('id');
            var blockid = that.attr('data-blockid');
            var itemid = that.attr('data-itemid');
            var snaptype = that.attr('data-type');
            var plotid = handleid.replace('snapshot-', '');

            html2canvas(document.querySelector("#" + plotid)).then(function(canvas) {
                var url = cfg.wwwroot + '/blocks/auditquiz_results/ajax/service.php';
                log.debug('url set ');

                // var feedbackdiv = document.getElementById('id-snapshot-feedback-' + userid + '-' + blockid);
                // feedbackdiv.appendChild(canvas);
                canvascontent = canvas.toDataURL();

                data = {
                    what: 'addsnapshot',
                    blockid: blockid,
                    itemid: itemid,
                    snaptype: snaptype,
                    imagedata: canvascontent
                };

                $.post(url, data, function(returndata) {
                    $('#id-auditquiz-snapshots-container-' + itemid + '-' + blockid).html(returndata);
                    // Display if at least one snapshot is commin in.
                    $('#id-auditquiz-snapshots-container-' + itemid + '-' + blockid).css('display', 'block');
                }, 'html');
            });
        },

        delete_image: function() {

            var that = $(this);
            var snapid = that.attr('data-fileid');
            var itemid = that.attr('data-itemid');
            var snaptype = that.attr('data-type');
            var blockid = that.attr('data-blockid');

            var url = cfg.wwwroot + '/blocks/auditquiz_results/ajax/service.php';
            data = {
                what: 'deletesnapshot',
                snapshotid: snapid,
                blockid: blockid,
                itemid: itemid,
                snaptype: snaptype,
            };

            $.post(url, data, function(returndata) {
                $('#id-auditquiz-snapshots-container-' + itemid + '-' + blockid).html(returndata);
            }, 'html');
        },

        change_user_sorting: function() {

            var that = $(this);
            var sortby = that.val();
            var blockid = that.attr('data-blockid');
            var courseid = that.attr('data-courseid');
            var view = that.attr('data-view');

            var url = cfg.wwwroot + '/blocks/auditquiz_results/coursereport.php';
            url += '?view=' + view;
            url += '&blockid=' + blockid;
            url += '&id=' + courseid;
            url += '&sort=' + sortby;
            window.location.href = url;
        }
    };

    return auditquizresults;

});