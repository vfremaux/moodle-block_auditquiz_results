<?php
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

defined('MOODLE_INTERNAL') || die();

define('PDF_WIDTH_FACTOR', 1.85);

// Protects core cron from reloading here the actualized TCPDF class.
require_once($CFG->dirroot.'/local/vflibs/tcpdflib.php');

/**
 * A4_embedded delivery report
 *
 * @package    tool
 * @subpackage delivery
 * @copyright  Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from view.php
}

function block_auditquiz_results_check_page_break(&$pdf, $y, &$isnewpage, $last = false) {
    static $pdfpage = 1;

    if ($y > 240) {
        if (!$last) {
            $pdf->writeHTMLCell(30, 0, 95, 280, get_string('pdfpage', 'block_auditquiz_results', $pdfpage), 0, 0, 0, true, 'C');
            $pdfpage++;
            $pdf->addPage('P', 'A4', true);
            $y = 70;
            // Add header image.
            block_auditquiz_results_print_header($pdf, 'inner');
            // Add footer image.
            block_auditquiz_results_print_footer($pdf);
            // Add images and lines.
            block_auditquiz_results_draw_frame($pdf);
            $isnewpage = true;
        }
    }

    if ($last) {
        $pdf->writeHTMLCell(30, 0, 95, 280, get_string('pdfpage', 'block_auditquiz_results', $pdfpage), 0, 0, 0, true, 'C');
    }

    return $y;
}

/**
 * Sends text to output given the following params.
 *
 * @param stdClass $pdf
 * @param int $x horizontal position
 * @param int $y vertical position
 * @param char $align L=left, C=center, R=right
 * @param string $font any available font in font directory
 * @param char $style ''=normal, B=bold, I=italic, U=underline
 * @param int $size font size in points
 * @param string $text the text to print
 */
function block_auditquiz_results_print_text(&$pdf, $text, $x, $y, $l = '', $h = '', $align = 'L', $font='freeserif', $style = '', $size=10) {

    if (preg_match('/^<h>/', $text)) {
        $text = str_replace('<h>', '', $text);
        $size += 2;
        $style = 'B';
    }

    $pdf->setFont($font, $style, $size);
    $pdf->writeHTMLCell($l, $h, $x, $y, $text, 0, 1, 0, true, $align);

    return $pdf->getY();
}

/**
 * Sends text to output given the following params.
 *
 * @param stdClass $pdf
 * @param int $y vertical position
 * @param int $table the table with all data
 * @return the new Y pos after the log line has been written
 */
function block_auditquiz_results_print_pdf_overheadline(&$pdf, $y, &$table){

    $x = 10;
    $pdf->SetXY($x, $y);
    $pdf->SetFontSize(10);
    $border = array('LTBR' => array('width' => 2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(255, 255, 255)));

    $i = 0;
    foreach ($table->pdfhead1 as $header) {
        if (!empty($table->pdfprintinfo[$i])) {
            list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfbgcolor1[$i]);
            $pdf->SetFillColor($r, $v, $b);
            list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfcolor1[$i], true);
            $pdf->SetTextColor($r, $v, $b);
            $cellsize = str_replace('%', '', $table->pdfsize1[$i]) * PDF_WIDTH_FACTOR;
            $pdf->writeHTMLCell($cellsize, 0, $x, $y, $header, $border, 0, 1, true, $table->pdfalign1[$i]);
            $x += $cellsize;
        }
        $i++;
    }

    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);

    return $pdf->getY();
}

/**
 * Sends text to output given the following params.
 *
 * @param stdClass $pdf
 * @param int $y vertical position
 * @param int $table the table with all data
 * @return the new Y pos after the log line has been written
 */
function block_auditquiz_results_print_pdf_headline($pdf, $y, &$table) {

    $x = 10;
    $pdf->SetXY($x, $y);
    $pdf->SetFontSize(10);
    $border = array('LTBR' => array('width' => 2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(255, 255, 255)));

    $i = 0;
    foreach ($table->pdfhead2 as $header) {
        if ($table->pdfprintinfo[$i]) {
            $cellsize = str_replace('%', '', $table->pdfsize2[$i]) * PDF_WIDTH_FACTOR;
            list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfbgcolor2[$i]);
            $pdf->SetFillColor($r, $v, $b);
            list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfcolor2[$i], true);
            $pdf->SetTextColor($r, $v, $b);
            $pdf->writeHTMLCell($cellsize, 0, $x, $y, $header, $border, 0, 1, true, $table->pdfalign2[$i]);
            $x += $cellsize;
        }
        $i++;
    }

    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);

    return $pdf->getY();
}

/**
 * Sends text to output given the following params.
 *
 * @param stdClass $pdf
 * @param int $y vertical position
 * @param int $table the table with all data
 * @return the new Y pos after the log line has been written
 */
function block_auditquiz_results_print_pdf_studentline($pdf, $y, $username) {

    $x = 10;
    $pdf->SetXY($x, $y);
    $pdf->SetFontSize(12);

    $pdf->SetFillColor(230);
    $pdf->SetTextColor(0);

    $pdf->writeHTMLCell($cellsize, 0, $x, $y, $username, null, 0, 1, true);

    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);

    return $pdf->getY();
}

/**
 * Sends text to output given the following params.
 *
 * @param stdClass $pdf
 * @param int $y vertical position
 * @param array $dataline the data to print
 * @param objectref $table the table with all data
 * @return the new Y pos after the log line has been written
 */
function block_auditquiz_results_print_pdf_dataline(&$pdf, $y, $dataline, &$table, $line) {

    $x = 10;
    $pdf->SetXY($x, $y);
    $pdf->SetFontSize(9);

    $i = 0;
    foreach ($dataline as $datum) {
        // debug_trace("Data Cell $i: ".$table->pdfprintinfo[$i]."<br/>\n");
        if ($table->pdfprintinfo[$i]) {
            // debug_trace("Printing Data Cell\n");
            $cellsize = str_replace('%', '', @$table->pdfsize2[$i]) * PDF_WIDTH_FACTOR;
            if (is_object($datum) || isset($span)) {
                // debug_trace("Data $i) Print start<br/>\n");
                if (!empty($datum->colspan)) {
                    // debug_trace("Data $i) init $spantoreach <br/>\n");
                    // This is a span start, save content and span to reach
                    $content = ''.@$datum->text;
                    $spantoreach = $datum->colspan;
                    $span = 1;
                    $align = $table->pdfalign2[$i];
                    $size = $cellsize;
                    $i++;
                    continue;

                } elseif (!isset($span)) {
                    // Non spanning single cell
                    // debug_trace("Data $i) normal out ($x, $y, $cellsize) with ".htmlentities($datum->text)."<br/>\n");
                    $pdf->writeHTMLCell($cellsize, 0, $x, $y, $datum->text, 0, 0, 0, true, @$table->pfdalign2[$i]);
                    $x += $cellsize;
                    $i++;
                    continue;
                }

                if ($span < $spantoreach) {
                    $span++;
                    // debug_trace("Data $i) Up span to $span<br/>\n");
                    $size += str_replace('%', '', $table->pdfsize2[$i]) * PDF_WIDTH_FACTOR;
                    $i++;
                    continue;
                }

                if ($span == $spantoreach) {
                    unset($spantoreach);
                    unset($span);
                    // debug_trace("Data $i) resolve at ($x,$y, $cellsize) with ".htmlentities($content)."<br/>\n");
                    $pdf->writeHTMLCell($size, 0, $x, $y, $content, 0, 0, 0, true, $align);
                    $x += $size;
                    $i++;
                    continue;
                }

                debug_trace("Data $i) Weird case<br/>\n");
            } else {
                // debug_trace("Data $i) scalar out ($x, $y, $cellsize) with ".htmlentities($datum)."<br/>\n");
                // $datum = ''.@$table->pdfdata[$line][$i];
                $pdf->writeHTMLCell($cellsize, 0, $x, $y, $datum, 0, 0, 0, true, $table->pdfalign2[$i]);
                $x += $cellsize;
            }
        }
        $i++;
    }

    return $pdf->getY();
}

/**
 * Sends text to output given the following params with a special summarizer styling (highlighted).
 *
 * @param stdClass $pdf
 * @param int $y vertical position
 * @param array $dataline the data to print
 * @param objectref $table the table with all data
 * @return the new Y pos after the log line has been written
 */
function block_auditquiz_results_print_pdf_sumline($pdf, $y, $dataline, &$table, $line) {

    $x = 10;
    $pdf->SetXY($x, $y);
    $pdf->SetFontSize(10);
    $border = array('LTBR' => array('width' => 2, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(255, 255, 255)));

    $i = 0;
    foreach ($dataline as $datum) {
        // debug_trace("Cell $i: ".$table->pdfprintinfo[$i]."<br/>\n");
        $cellsize = str_replace('%', '', @$table->pdfsize2[$i]) * PDF_WIDTH_FACTOR;
        if (is_object($datum) || isset($span)) {
            if ($table->pdfprintinfo[$i]) {
                if (!empty($datum->colspan)) {
                    // This is a span start, save content and span to reach
                    $content = $datum->text;
                    $spantoreach = $datum->colspan;
                    $span = 1;
                    $align = $table->align[$i];
                    $size = $cellsize;
                    $sumbgcolor = @$table->pdfbgcolor3[$i];
                    $sumcolor = @$table->pdfcolor3[$i];
                    // debug_trace("$i) init $spantoreach <br/>\n");
                    $i++;
                    continue;
                } elseif(!isset($span)) {
                    // Non spanning single cell
                    // debug_trace("$i) normal out ($x, $y, $cellsize) with ".htmlentities($datum->text)."<br/>\n");
                    list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfbgcolor3[$i]);
                    $pdf->SetFillColor($r, $v, $b);
                    list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfcolor3[$i], true);
                    $pdf->SetTextColor($r, $v, $b);
                    $pdf->writeHTMLCell($cellsize, 15, $x, $y, $datum->text, $border, 0, 1, true, @$table->pdfalign3[$i]);
                    $x += $cellsize;
                    $i++;
                    continue;
                }
                if ($span < $spantoreach) {
                    $span++;
                    debug_trace("$i) up $span<br/>\n");
                    $size += str_replace('%', '', $table->pdfsize2[$i]) * PDF_WIDTH_FACTOR;
                }
                if ($span == $spantoreach) {
                    unset($spantoreach);
                    unset($span);
                    // debug_trace("$i) resolve at ($x,$y, $cellsize) with ".htmlentities($content)."<br/>\n");
                    list($r, $v, $b) = tcpdf_decode_html_color($sumbgcolor);
                    $pdf->SetFillColor($r, $v, $b);
                    list($r, $v, $b) = tcpdf_decode_html_color($sumcolor, true);
                    $pdf->SetTextColor($r, $v, $b);
                    $pdf->writeHTMLCell($size, 15, $x, $y, $content, $border, 0, 1, true, $align);
                    $x += $size;
                }
            }
        } else {
            list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfbgcolor3[$i]);
            $pdf->SetFillColor($r, $v, $b);
            list($r, $v, $b) = tcpdf_decode_html_color(@$table->pdfcolor3[$i], true);
            $pdf->SetTextColor($r, $v, $b);
            $pdf->writeHTMLCell($cellsize, 15, $x, $y, $datum, $border, 0, 1, true, $table->pdfalign3[$i]);
            // debug_trace("$i) scalar out ($x, $y, $cellsize) with ".htmlentities($datum)."<br/>\n");
            $x += $cellsize;
        }
        $i++;
    }

    $pdf->SetFillColor(255);
    $pdf->SetTextColor(0);

    return $pdf->getY();
}

/**
 * Creates rectangles for line border for A4 size paper.
 *
 * @param stdClass $pdf
 */
function block_auditquiz_results_draw_frame(&$pdf) {

    // Create outer line border in selected color.
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(200);
    $pdf->Rect(10, 10, 190, 277);
}

/**
 * Prints logo image from the borders folder in PNG or JPG formats.
 *
 * @param stdClass $pdf;
 */
function block_auditquiz_results_print_header(&$pdf, $alternateheader = false) {
    global $CFG;

    $fs = get_file_storage();
    $systemcontext = context_system::instance();

    if ($alternateheader) {
        $files = $fs->get_area_files($systemcontext->id, 'block_auditquiz_results', 'pdfreportinnerheader', 0);
    
        if (!empty($files)) {
            $headerfile = array_pop($files);
        } else {
            // Take cover header as default if exists.
            $files = $fs->get_area_files($systemcontext->id, 'block_auditquiz_results', 'pdfreportheader', 0);
        
            if (!empty($files)) {
                $headerfile = array_pop($files);
            } else {
                return;
            }
        }
    } else {
        $files = $fs->get_area_files($systemcontext->id, 'block_auditquiz_results', 'pdfreportheader', 0);
    
        if (!empty($files)) {
            $headerfile = array_pop($files);
        } else {
            return;
        }
    }

    $contenthash = $headerfile->get_contenthash();
    $pathhash = tcpdf_get_path_from_hash($contenthash);
    $realpath = $CFG->dataroot.'/filedir/'.$pathhash.'/'.$contenthash;

    $size = getimagesize($realpath);

    // Converts 72 dpi images into mm.
    $pdf->Image($realpath, 20, 20, $size[0] / 2.84 / PDF_WIDTH_FACTOR, $size[1] / 2.84 / PDF_WIDTH_FACTOR);
}

/**
 * Prints logo image from the borders folder in PNG or JPG formats.
 *
 * @param stdClass $pdf;
 */
function block_auditquiz_results_print_footer(&$pdf) {
    global $CFG;

    $fs = get_file_storage();
    $systemcontext = context_system::instance();

    $files = $fs->get_area_files($systemcontext->id, 'block_auditquiz_results', 'pdfreportfooter', 0);

    if (!empty($files)) {
        $footerfile = array_pop($files);
    } else {
        return;
    }

    $contenthash = $footerfile->get_contenthash();
    $pathhash = tcpdf_get_path_from_hash($contenthash);
    $realpath = $CFG->dataroot.'/filedir/'.$pathhash.'/'.$contenthash;

    $size = getimagesize($realpath);

    // Converts 72 dpi images into mm.
    $pdf->Image($realpath, 20, 260, $size[0] / 2.84 / PDF_WIDTH_FACTOR, $size[1] / 2.84 / PDF_WIDTH_FACTOR);
}


