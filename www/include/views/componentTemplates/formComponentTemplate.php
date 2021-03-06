<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

if (!isset($centreon)) {
    exit();
}

/*
 * Load 2 general options
 */
$l_general_opt = array();
$DBRESULT = $pearDB->query("SELECT * FROM options WHERE `key` RLIKE '^color_(warn|crit)'");
while ($opt = $DBRESULT->fetchRow()) {
    $l_general_opt[$opt['key']] = $opt['value'];
}
$DBRESULT->closeCursor();

$compo = array();
if (($o == "c" || $o == "w") && $compo_id) {
    $res = $pearDB->query("SELECT * FROM giv_components_template WHERE compo_id = '" . $compo_id . "' LIMIT 1");
    /*
	 * Set base value
	 */
    $tab = $res->fetchRow();
    $compo = array_map("myDecode", $tab);
    unset($compo['host_id']);
    unset($compo['service_id']);
    $res->closeCursor();

    $hs_data = array();
    $query = "SELECT id FROM index_data WHERE host_id = '" . $compo["host_id"] .
        "' AND service_id = '" . $compo["service_id"] . "' LIMIT 1";
    $res = $pearDBO->query($query);
    $tab = $res->fetchRow();
    if (is_array($tab)) {
        $hs_data = array_map("myDecode", $tab);
        $compo["index_id"] = $hs_data["id"];
        $res->closeCursor();
    } else {
        $compo["index_id"] = 0;
    }
}

/*
 * Graphs comes from DB -> Store in $graphs Array
 */
$graphs = array();
$res = $pearDB->query("SELECT graph_id, name FROM giv_graphs_template ORDER BY name");
while ($graph = $res->fetchRow()) {
    $graphs[$graph["graph_id"]] = $graph["name"];
}
$res->closeCursor();

/*
 * List of known data sources
 */
$datasources = array();
$query = "SELECT `metric_name`, `unit_name` FROM `metrics` GROUP BY `metric_name` ORDER BY `metric_name`";
$DBRESULT = $pearDBO->query($query);
while ($row = $DBRESULT->fetchRow()) {
    $datasources[$row["metric_name"]] = $row["metric_name"];
    if (isset($row["unit_name"]) && $row["unit_name"] != "") {
        $datasources[$row["metric_name"]] .= " (" . $row["unit_name"] . ")";
    }
}
unset($row);
$DBRESULT->closeCursor();

/*
 * Define Styles
 */
$attrsText = array("size" => "40");
$attrsText2 = array("size" => "10");
$attrsTextarea = array("rows" => "4", "cols" => "60");
$eTemplate = '<table><tr><td><div class="ams">{label_2}</div>{unselected}</td><td align="center">{add}<br />' .
    '<br /><br />{remove}</td><td><div class="ams">{label_3}</div>{selected}</td></tr></table>';

$availableRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_service&action=list';
$defaultRoute = './include/common/webServices/rest/internal.php?object=centreon_configuration_objects' .
    '&action=defaultValues&target=graphCurve&field=host_id&id=' . $compo_id;
$attrServices = array(
    'datasourceOrigin' => 'ajax',
    'availableDatasetRoute' => $availableRoute,
    'defaultDatasetRoute' => $defaultRoute,
    'linkedObject' => 'centreonService',
    'multiple' => false
);

/*
 * Form begin
 */
$form = new HTML_QuickForm('Form', 'post', "?p=" . $p);
if ($o == "a") {
    $form->addElement('header', 'ftitle', _("Add a Data Source Template"));
} elseif ($o == "c") {
    $form->addElement('header', 'ftitle', _("Modify a Data Source Template"));
} elseif ($o == "w") {
    $form->addElement('header', 'ftitle', _("View a Data Source Template"));
}

/*
 *  Basic information
 */
$form->addElement('header', 'information', _("General Information"));
$form->addElement('header', 'options', _("Display Optional Modifier"));
$form->addElement('header', 'color', _("Colors"));
$form->addElement('header', 'legend', _("Legend"));
$form->addElement('text', 'name', _("Template Name"), $attrsText);
$form->addElement('checkbox', 'ds_stack', _("Stack"));

for ($cpt = 1; $cpt <= 100; $cpt++) {
    $orders[$cpt] = $cpt;
}
$form->addElement('select', 'ds_order', _("Order"), $orders);

$form->addElement('static', 'hsr_text', _("Choose a service if you want a specific curve for it."));
$form->addElement('select2', 'host_id', _("Linked Host Services"), array(), $attrServices);

$form->addElement('text', 'ds_name', _("Data Source Name"), $attrsText);
$form->addElement('select', 'datasources', null, $datasources);

$l_dsColorList = array(
    "ds_color_line" => array("label" => _("Line color"), "color" => "#0000FF"),
    "ds_color_area" => array("label" => _("Area color"), "color" => "#FFFFFF"),
    "ds_color_area_warn" => array("label" => _("Warning Area color"), "color" => $l_general_opt["color_warning"]),
    "ds_color_area_crit" => array("label" => _("Critical Area color"), "color" => $l_general_opt["color_critical"])
);

foreach ($l_dsColorList as $l_dsColor => $l_dCData) {
    if (isset($compo[$l_dsColor]) && !empty($compo[$l_dsColor])) {
        $l_hxColor = $compo[$l_dsColor];
    } else {
        $l_hxColor = $l_dCData["color"];
    }
    $attColText = array(
        "value" => $l_hxColor,
        "size" => "7",
        "maxlength" => "7",
        "style" => "text-align: center; font-size: 11px; font-family: 'Courier New';"
    );
    $attColText = array("value" => $l_hxColor, "size" => "7", "maxlength" => "7", "style" => "text-align: center;");
    $form->addElement('text', $l_dsColor, $l_dCData["label"], $attColText);

    $attColAreaR = array(
        "style" => "width:50px; height:15px; background-color: " . $l_hxColor .
            "; border-width:0px; padding-bottom:2px;"
    );
    $attColAreaW = array(
        "style" => "width:50px; height:15px; background-color: " . $l_hxColor .
            "; border-width:0px; padding-bottom:2px;"
    );
    $form->addElement('button', $l_dsColor . '_color', "", $attColAreaW);
    $form->addElement('button', $l_dsColor . '_read', "", $attColAreaR);
}

$attTransext = array("size" => "2", "maxlength" => "3", "style" => "text-align: center;");
$form->addElement('text', 'ds_transparency', _("Transparency"), $attTransext);

$form->addElement('checkbox', 'ds_filled', _("Filling"));
$form->addElement('checkbox', 'ds_max', _("Print Max value"));
$form->addElement('checkbox', 'ds_min', _("Print Min value"));
$form->addElement('checkbox', 'ds_minmax_int', _('Round the min and max'));
$form->addElement('checkbox', 'ds_average', _("Print Average"));
$form->addElement('checkbox', 'ds_last', _("Print Last Value"));
$form->addElement('checkbox', 'ds_total', _("Print Total Value"));
$form->addElement('checkbox', 'ds_invert', _("Invert"));
$form->addElement('checkbox', 'default_tpl1', _("Default Centreon Graph Template"));
$form->addElement('select', 'ds_tickness', _("Thickness"), array("1" => "1", "2" => "2", "3" => "3"));
$form->addElement('text', 'ds_legend', _("Legend Name"), $attrsText);
$form->addElement('checkbox', 'ds_hidecurve', _("Display Only The Legend"));
$form->addElement(
    'select',
    'ds_jumpline',
    _("Empty Line After This Legend"),
    array("0" => "0", "1" => "1", "2" => "2", "3" => "3")
);
$form->addElement('textarea', 'comment', _("Comments"), $attrsTextarea);

/*
 * Components linked with
 */
$form->addElement('header', 'graphs', _("Graph Choice"));

$form->addElement('hidden', 'compo_id');
$redirect = $form->addElement('hidden', 'o');
$redirect->setValue($o);

function testFilled()
{
}

function color_line_enabled($values)
{
    if (isset($values[0]['ds_color_line_mode']) && $values[0]['ds_color_line_mode'] == '1') {
        return true;
    }
    if (!isset($values[1]) || $values[1] == '') {
        return false;
    }
    return true;
}

/*
 * Form Rules
 */
$form->registerRule('existName', 'callback', 'NameHsrTestExistence');
$form->registerRule('existDs', 'callback', 'DsHsrTestExistence');

$form->applyFilter('__ALL__', 'myTrim');
$form->addRule('name', _("Compulsory Name"), 'required');
$form->addRule('ds_name', _("Required Field"), 'required');
$form->addRule('name', _("Name already in use for this Host/Service"), 'existName');
$form->addRule('ds_name', _("Data Source already in use for this Host/Service"), 'existDs');
$color_mode[] = HTML_QuickForm::createElement('radio', 'ds_color_line_mode', null, _("Random"), '1');
$color_mode[] = HTML_QuickForm::createElement('radio', 'ds_color_line_mode', null, _("Manual"), '0');
$form->addGroup($color_mode, 'ds_color_line_mode', _("Color line mode"));
$form->registerRule('color_line_enabled', 'callback', 'color_line_enabled');
$form->addRule(
    array('ds_color_line_mode', 'ds_color_line'),
    _("Required Field"),
    'color_line_enabled'
);

$form->registerRule('checkColorFormat', 'callback', 'checkColorFormat');

$form->addRule('ds_color_line', _("Bad Format: start color by #"), 'checkColorFormat');
$form->addRule('ds_color_area', _("Bad Format: start color by #"), 'checkColorFormat');
$form->addRule('ds_color_area_warn', _("Bad Format: start color by #"), 'checkColorFormat');
$form->addRule('ds_color_area_crit', _("Bad Format: start color by #"), 'checkColorFormat');

$form->setRequiredNote("<font style='color: red;'>*</font>&nbsp;" . _("Required fields"));

/*
 * Smarty template Init
 */
$tpl = new Smarty();
$tpl = initSmartyTpl($path, $tpl);

if ($o == "w") {
    /*
	 * Just watch
	 */
    $form->addElement(
        "button",
        "change",
        _("Modify"),
        array("onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&compo_id=" . $compo_id . "'")
    );
    $form->setDefaults($compo);
    $form->freeze();
} elseif ($o == "c") {
    /*
	 * Modify
	 */
    $subC = $form->addElement('submit', 'submitC', _("Save"), array("class" => "btc bt_success"));
    $res = $form->addElement('reset', 'reset', _("Reset"), array(
        "onClick" => "javascript:resetLists(" . $compo["host_id"] . "," . $compo["index_id"] . ")",
        "class" => "btc bt_default"
    ));
    $form->setDefaults($compo);
} elseif ($o == "a") {
    /*
	 * Add
	 */
    $subA = $form->addElement('submit', 'submitA', _("Save"), array("class" => "btc bt_success"));
    $res = $form->addElement(
        'reset',
        'reset',
        _("Reset"),
        array("onClick" => "javascript:resetLists(0,0)", "class" => "btc bt_default")
    );
    $form->setDefaults(array(
        "ds_color_area" => "#FFFFFF",
        "ds_color_area_warn" => "#F8C706",
        "ds_color_area_crit" => "#F91E05",
        "ds_color_line" => "#0000FF",
        "ds_color_line_mode" => '0',
        "ds_transparency" => "80",
        "ds_average" => true,
        "ds_last" => true
    ));
}
if ($o == "c" || $o == "a") {
    ?>
    <script type='text/javascript'>
        function insertValueQuery() {
            var e_input = document.Form.ds_name;
            var e_select = document.getElementById('sl_list_metrics');
            var sd_o = e_select.selectedIndex;
            if (sd_o != 0) {
                var chaineAj = '';
                chaineAj = e_select.options[sd_o].text;
                chaineAj = chaineAj.replace(/\s(\[[CV]DEF\]|)\s*$/, "");
                e_input.value = chaineAj;
            }
        }

        function popup_color_picker(t, name) {
            var width = 318;
            var height = 314;
            var hcolor = '000000';
            var i_elem = document.getElementsByName(t + '_color').item(0);
            if (i_elem != null) {
                var bckcolor = i_elem.style.backgroundColor;
                var exp = new RegExp('rgb', 'g');
                if (exp.test(bckcolor)) {
                    exp = new RegExp('[0-9]+', 'g');
                    var tab_rgb = bckcolor.match(exp);
                    hcolor = dechex(parseInt(tab_rgb[0])) + dechex(parseInt(tab_rgb[1])) + dechex(parseInt(tab_rgb[2]));
                } else {
                    hcolor = bckcolor.substr(1, 6);
                }
            }
            Modalbox.show('./include/common/javascript/color_picker_mb.php?name=' + name, {
                title: "<?php echo _('Pick a color') ?>",
                width: width,
                height: height,
                afterLoad: function () {
                    cp_init(t, hcolor);
                }
            });
        }
    </script><?php
}
$tpl->assign('msg', array("changeL" => "?p=" . $p . "&o=c&compo_id=" . $compo_id, "changeT" => _("Modify")));

$tpl->assign("sort1", _("Properties"));
$tpl->assign("sort2", _("Graphs"));
// prepare help texts
$helptext = "";
include_once("help.php");
foreach ($help as $key => $text) {
    $helptext .= '<span style="display:none" id="help:' . $key . '">' . $text . '</span>' . "\n";
}
$tpl->assign("helptext", $helptext);

$valid = false;
if ($form->validate()) {
    $compoObj = $form->getElement('compo_id');
    if ($form->getSubmitValue("submitA")) {
        $compoObj->setValue(insertComponentTemplateInDB());
    } elseif ($form->getSubmitValue("submitC")) {
        updateComponentTemplateInDB($compoObj->getValue());
    }
    $o = "w";
    $form->addElement(
        "button",
        "change",
        _("Modify"),
        array("onClick" => "javascript:window.location.href='?p=" . $p . "&o=c&compo_id=" . $compoObj->getValue() . "'")
    );
    $form->freeze();
    $valid = true;
}
$action = $form->getSubmitValue("action");
if ($valid) {
    require_once("listComponentTemplates.php");
} else {
    /*
	 * Apply a template definition
	 */
    $renderer = new HTML_QuickForm_Renderer_ArraySmarty($tpl, true);
    $renderer->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $renderer->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $form->accept($renderer);
    $tpl->assign('form', $renderer->toArray());
    $tpl->assign('o', $o);
    $tpl->display("formComponentTemplate.ihtml");
}
$vdef = 0; /* don't list VDEF in metrics list */

include_once("./include/views/graphs/common/makeJS_formMetricsList.php");
if ($o == "c" || $o == "w") {
    isset($_POST["host_id"]) && $_POST["host_id"] != null
        ? $host_service_id = $_POST["host_id"]
        : $host_service_id = $compo["host_id"];
} elseif ($o == "a") {
    isset($_POST["host_id"]) && $_POST["host_id"] != null
        ? $host_service_id = $_POST["host_id"]
        : $host_service_id = 0;
}
?>

<script type="text/javascript">
    update_select_list('<?php echo $host_service_id;?>');

    jQuery("#host_id").on('change', function () {
        update_select_list(this.value);
    });
</script>
