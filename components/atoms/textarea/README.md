# Textarea

Text area type form element.

**Bootstrap `~5.3.0`** Textareas
https://getbootstrap.com/docs/5.3/forms/floating-labels/#textareas
https://getbootstrap.com/docs/5.3/forms/floating-labels/#disabled

**Drupal `~10.1.0`** core has [css styles for components](https://git.drupalcode.org/project/drupal/-/tree/10.1.x/core/modules/system/css/components)

* [Resizable textareas](https://git.drupalcode.org/project/drupal/-/blob/10.1.x/core/modules/system/css/components/resize.module.css)
* [Utilizing more properties for the Textarea form element](https://git.drupalcode.org/project/drupal/-/blob/10.1.x/core/includes/form.inc#L382)
```
- element: An associative array containing the properties of the element.
Properties used: #title, #value, #description, #rows, #cols, #maxlength, #placeholder, #required, #attributes, #resizable.
```
* [From the The TEXTAREA element by W3](https://www.w3.org/TR/html401/interact/forms.html#h-17.7)


## Properties:
* `resizable`: (none|vertical|horizontal|both) An indicator for whether the textarea is resizable.
* `required`: (true|false) An indicator for whether the textarea is required.
* `value`: The textarea content.
* `rows`: Specifies the number of visible text lines.
* `cols`: Specifies the visible width in average character widths.
* `maxlength`: Specifies the maximum length (in characters) of a text area. By default, the maximum is 524,288 characters.
* `placeholder`: Specifies a short hint that describes the expected value of a text area.
* `readonly`: Specifies whether the control may be modified by the user.
* `disabled`: Disables the control for user input.
* `wrapper_html_tag`: (div|span) The HTML tag for the wrapper.
* `wrapper_utility_classes`: An array of utility classes. Use to add extra Bootstrap utility classes or custom CSS classes over the wrapper div to this component.
* `utility_classes`: An array of utility classes. Use to add extra Bootstrap utility classes or custom CSS classes over the form element to this component.

## Attributes:
* `wrapper_attributes`: A list of HTML attributes for the wrapper element.
* `attributes`: A list of HTML attributes for the <textarea> element.

## Slots:
* N/A


### Examples

#### Example #1: Basic use to match Drupal core and stable9 theme
```
{#
/**
 * @file
 * Theme override for a 'textarea' #type form element.
 *
 * Available variables
 * - wrapper_attributes: A list of HTML attributes for the wrapper element.
 * - attributes: A list of HTML attributes for the <textarea> element.
 * - resizable: An indicator for whether the textarea is resizable.
 * - required: An indicator for whether the textarea is required.
 * - value: The textarea content.
 *
 * @see template_preprocess_textarea()
 */
#}
{% include 'varbase_components:textarea' %}
```
Used in [Vartheme BS5](https://github.com/Vardot/vartheme_bs5/blob/3.0.x/templates/form/textarea.html.twig)

#### Example #2: Textarea - Resize both
```
{%
  include 'varbase_components:textarea' with {
    resizable: 'both'
    required: true,
    value: "Vivamus hendrerit est sit amet vehicula tempus. Fusce non sollicitudin massa. Nam sollicitudin mollis ullamcorper.",
    rows: 5,
    cols: 80,
    maxlength: 300,
    placeholder: "Type test in this text area",
    wrapper_html_tag: "div",
    wrapper_utility_classes: ['p-sm-3'],
    utility_classes: ['mt-3']
  } 
%}
```

#### Example #3: Textarea - Resize none readonly but not disabled
```
{%
 include 'varbase_components:textarea' with {
    resizable: 'none'
    required: true,
    value: "Vivamus hendrerit est sit amet vehicula tempus. Fusce non sollicitudin massa. Nam sollicitudin mollis ullamcorper.",
    readonly: true,
    disabled: false
  } 
%}
```
