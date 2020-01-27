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

define(['jquery', 'core/log', 'core/config'], function($, log, cfg) {

    var auditquiz_results = {

        init: function() {
            $('.auditquiz-results-unbind').bind('click', this.unbind_course);
            // $('.auditquiz-snapshot-btn').bind('click', this.send_image);
            // $('.auditquiz-snapshot-delete-btn').bind('click', this.delete_image);

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
            });
        },
    };

    return auditquiz_results;

});