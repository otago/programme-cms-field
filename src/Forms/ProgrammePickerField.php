<?php

namespace Otago\ProgrammeCmsField\Forms;

use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\Requirements;

/**
 * CMS form field for picking a programme from the online.op.ac.nz GraphQL API.
 *
 * Renders an accessible search-as-you-type combobox that proxies results through
 * the SilverStripe admin (/admin/programme-options/list). Pass the DB field name
 * directly — the hidden input is managed internally.
 *
 * Usage in getCMSFields():
 *
 *   $fields->addFieldToTab('Root.Main', ProgrammePickerField::create('ProgrammeID'));
 *
 * The JS/CSS requirements are loaded automatically when the field is rendered.
 */
final class ProgrammePickerField extends DropdownField
{
    private static string $module_path = 'otago/programme-cms-field:';

    public function __construct(string $name, ?string $title = null)
    {
        parent::__construct($name, $title ?? 'Programme', []);
        $this->setHasEmptyDefault(true);
        $this->setEmptyString('- Select a programme -');
        $this->addExtraClass('js-programmeid-dropdown');
        $this->setAttribute('data-remote-endpoint', '/admin/programme-options/list');
        $this->setAttribute('data-page-size', '25');
        $this->setAttribute('data-placeholder', 'Search programmes…');
        $this->setAttribute('data-hidden-field', $name);
    }

    /**
     * Ensure the currently selected value is present in the source map so
     * server-side validation passes, even though options are loaded dynamically.
     *
     * @return array<string,string>
     */
    public function getSource(): array
    {
        $src = parent::getSource() ?: [];
        $val = (string) $this->getValue();
        if ($val !== '' && $val !== '0' && !array_key_exists($val, $src)) {
            $src = ['' => $this->getEmptyString(), $val => "Selected ID {$val}"] + $src;
        }
        return $src;
    }

    /**
     * Render a hidden input (carries the real DB value) followed by the picker
     * select (which uses a __picker name suffix so SilverStripe ignores it on save).
     */
    public function Field($properties = [])
    {
        self::loadRequirements();

        $realName = $this->name;
        $realId   = $this->ID();

        $val          = htmlspecialchars((string) ($this->Value() ?: ''), ENT_QUOTES, 'UTF-8');
        $escapedName  = htmlspecialchars($realName, ENT_QUOTES, 'UTF-8');
        $escapedId    = htmlspecialchars($realId, ENT_QUOTES, 'UTF-8');
        $hiddenHTML   = "<input type=\"hidden\" id=\"{$escapedId}\" name=\"{$escapedName}\" value=\"{$val}\" />";

        // Swap name so <select> doesn't shadow the hidden field in the POST.
        $this->name = $realName . '__picker';
        $selectHTML = (string) parent::Field($properties);
        $this->name = $realName;

        return DBHTMLText::create()->setValue($hiddenHTML . $selectHTML);
    }

    /**
     * Load all JS/CSS requirements for this field.
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
