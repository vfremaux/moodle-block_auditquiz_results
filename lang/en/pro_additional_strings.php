<<<<<<< HEAD
<?php

$string['plugindist'] = 'Plugin distribution';
$string['plugindist_desc'] = '
<p>This plugin is the community version and is published for anyone to use as is and check the plugin\'s
core application. A "pro" version of this plugin exists and is distributed under conditions to feed the life cycle, upgrade, documentation
and improvement effort.</p>
<p>Please contact one of our distributors to get "Pro" version support.</p>
<p><a href="http://www.mylearningfactory.com/index.php/documentation/Distributeurs?lang=en_utf8">MyLF Distributors</a></p>';

// Caches.
$string['cachedef_pro'] = 'Caches some pro related options and data';

require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php'); // to get xx_supports_feature();
if ('pro' == block_auditquiz_results_supports_feature()) {
    include($CFG->dirroot.'/blocks/auditquiz_results/pro/lang/en/pro.php');
}
=======
<?php

$string['plugindist'] = 'Plugin distribution';
$string['plugindist_desc'] = '
<p>This plugin is the community version and is published for anyone to use as is and check the plugin\'s
core application. A "pro" version of this plugin exists and is distributed under conditions to feed the life cycle, upgrade, documentation
and improvement effort.</p>
<p>Please contact one of our distributors to get "Pro" version support.</p>
<p><a href="http://www.mylearningfactory.com/index.php/documentation/Distributeurs?lang=en_utf8">MyLF Distributors</a></p>';

// Caches.
$string['cachedef_pro'] = 'Caches some pro related options and data';

require_once($CFG->dirroot.'/blocks/auditquiz_results/lib.php'); // to get xx_supports_feature();
if ('pro' == block_auditquiz_results_supports_feature()) {
    include($CFG->dirroot.'/blocks/auditquiz_results/pro/lang/en/pro.php');
}
>>>>>>> c432977fa6f6278cb577e444ef65a0869db15946
