# Gravity Forms Repeater Field Add-on

A robust repeater field add-on for Gravity Forms that allows grouping and repeating form sections.

## Features

- **Group Field**: Create logical groupings of form fields with built-in repeater functionality
- **Repeater Functionality**: Allow users to add/remove multiple instances of groups
- **Fieldset Management**: Automatic opening/closing of HTML fieldsets
- **Slider Animation**: Smooth transitions between repeater instances
- **Conditional Logic**: Full support for conditional logic on repeated fields
- **Admin Display**: View submission data grouped by repeater instances
- **Responsive Design**: Mobile-friendly controls and layout

## Installation

1. Upload the plugin files to `/wp-content/plugins/gravityforms-repeater-field/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure Gravity Forms is installed and activated

## Usage

### Creating a Repeater Group

1. In the Gravity Forms form editor, go to the "Advanced Fields" section
2. Add a "Group" field from the Advanced Fields
3. The Group field is automatically repeatable - no additional settings needed
4. Add your form fields after the group field
5. The group will automatically wrap fields in a fieldset

### Repeater Controls

When a Group field is added, users will see:
- **Add (+)**: Create a new instance of the group
- **Remove (-)**: Delete the current instance (if more than one exists)
- **Previous (←)**: Navigate to the previous instance
- **Next (→)**: Navigate to the next instance

### Field Management

- All fields within a repeater group are automatically duplicated
- Field names are updated to use array notation (`field_name[]`)
- Field IDs are updated to include instance index for uniqueness
- Conditional logic is preserved across all instances

## Development

### File Structure

```
gravityforms-repeater-field/
├── src/
│   ├── Classes/
│   │   ├── Core.php
│   │   ├── SectionField.php
│   │   ├── Assets.php
│   │   └── AdminDisplay.php
│   ├── Assets/
│   │   ├── css/
│   │   │   ├── frontend.css
│   │   │   ├── form.css
│   │   │   └── admin.css
│   │   └── js/
│   │       ├── frontend.js
│   │       ├── form.js
│   │       └── admin.js
│   └── Views/
│       └── repeater-setting.php
├── composer.json
└── gravityforms-repeater-field.php
```

### Hooks and Filters

#### Actions
- `gf_repeater_field_init` - Fired when the plugin initializes
- `gf_repeater_field_before_render` - Before rendering a repeater field
- `gf_repeater_field_after_render` - After rendering a repeater field

#### Filters
- `gf_repeater_field_settings` - Modify repeater field settings
- `gf_repeater_field_display_value` - Customize how repeater data is displayed
- `gf_repeater_field_validation` - Add custom validation for repeater fields

## Requirements

- WordPress 5.0 or higher
- Gravity Forms 2.5 or higher
- PHP 7.4 or higher

## Changelog

### 1.0.2
- Enhancements to admin rendering and email display.
- Fix issue with file upload fields.

### 1.0.1
- Added Merge Tag Formatting

### 1.0.0
- Initial release
- Section field with repeater functionality
- Fieldset management
- Slider animations
- Admin display integration
- Conditional logic support

## Support

For support and feature requests, please contact Briantics, Inc. at https://b7s.co

## License

GPL v2 or later - https://www.gnu.org/licenses/gpl-2.0.html
