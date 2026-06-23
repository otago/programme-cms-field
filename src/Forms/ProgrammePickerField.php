<?php

namespace Otago\ProgrammeCmsField\Forms;

use SilverStripe\Forms\DropdownField;
use SilverStripe\View\Requirements;

/**
 * CMS form field for picking a programme from the online.op.ac.nz GraphQL API.
 *
 * Renders an accessible search-as-you-type combobox that proxies results through
 * the SilverStripe admin (/admin/programme-options/list). The selected integer
 * Programme ID is stored via a hidden field whose name is configured via the
 * data-hidden-field attribute (default: "ProgrammeID").
 *
 * Usage in getCMSFields():
 *
 *   $fields->addFieldToTab('Root.Main', ProgrammePickerField::create('ProgrammeIDPicker')
 *       ->setAttribute('data-hidden-field', 'ProgrammeID')
 *   );
 *   $fields->addFieldToTab('Root.Main', HiddenField::create('ProgrammeID', 'Programme ID'));
 *
 * The JS/CSS requirements are loaded automatically when the field is rendered.
 */
final class ProgrammePickerField extends DropdownField
{
    private static string $module_path = 'otago/programme-cms-field:';

    /**
     * @param string|null $name
     * @param string|null $title
     * @param mixed|null  $value
     */
    public function __construct($name = 'ProgrammeIDPicker', $title = 'Programme', $value = null)
    {
        parent::__construct($name, $title, []);
        $this->setHasEmptyDefault(true);
        $this->setEmptyString('- Select a programme -');
        $this->addExtraClass('js-programmeid-dropdown');
        $this->setAttribute('data-remote-endpoint', '/admin/programme-options/list');
        $this->setAttribute('data-page-size', '25');
        $this->setAttribute('data-placeholder', 'Search programmes…');
    }

    /**
     * Ensure the currently selected value is present in the source map so
     * server-side validation passes, even though options are loaded dynamically.
     *
     * @return array<string,string>
     */
    public function getSource()
    {
        $src = parent::getSource() ?: [];
        $val = (string) $this->getValue();
        if ($val === '' && isset($_POST)) {
            $name = $this->getName();
            if ($name && array_key_exists($name, $_POST)) {
                $raw = $_POST[$name];
                $val = is_array($raw) ? (string) reset($raw) : (string) $raw;
            }
        }
        if ($val !== '' && !array_key_exists($val, $src)) {
            $src = ['' => $this->getEmptyString(), $val => "Selected ID {$val}"] + $src;
        }
        return $src;
    }

    /**
     * Load all JS and CSS requirements when the field renders.
     */
    public function Field($properties = [])
    {
        self::loadRequirements();
        return parent::Field($properties);
    }

    /**
     * Manually load all JS/CSS requirements for this field.
     * Called automatically by Field() — only needed if you are rendering
     * the field outside the normal form context.
     */
    public static function loadRequirements(): void
    {
        $m = self::$module_path;
        Requirements::javascript("{$m}client/js/programmeid-dropdown/constants.js");
        Requirements::javascript("{$m}client/js/programmeid-dropdown/state.js");
        Requirements::javascript("{$m}client/js/programmeid-dropdown/api.js");
        Requirements::javascript("{$m}client/js/programmeid-dropdown/dom.js");
        Requirements::javascript("{$m}client/js/programmeid-dropdown/index.js");
        Requirements::css("{$m}client/css/programmeid-dropdown/variables.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/base.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/toolbar.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/search.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/list.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/status.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/spinner.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/accessibility.css");
        Requirements::css("{$m}client/css/programmeid-dropdown/utilities.css");
    }
}
