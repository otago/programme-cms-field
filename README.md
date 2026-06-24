# programme-cms-field

SilverStripe CMS field for picking a programme from the [online.op.ac.nz](https://online.op.ac.nz) GraphQL API.

Renders an accessible search-as-you-type combobox in the CMS. Results are proxied through a SilverStripe controller so the API endpoint is never exposed to the browser directly.

## Requirements

- SilverStripe CMS 5 or 6
- `OP_APPLICATIONS_GRAPHQL_ENDPOINT` environment variable pointing at the GraphQL API

## Installation

```bash
composer require otago/programme-cms-field
```

## Usage

Add the field to any `DataObject` that has an integer `ProgrammeID` column:

```php
use Otago\ProgrammeCmsField\Forms\ProgrammePickerField;

public function getCMSFields(): FieldList
{
    $fields = parent::getCMSFields();
    $fields->addFieldToTab('Root.Main', ProgrammePickerField::create('ProgrammeID'));
    return $fields;
}
```

The hidden input and JS/CSS requirements are managed automatically.
