# Heading

All HTML headings, `<h1>` through `<h6>`, are available.

> #### [Bootstrap ~5.3.0 Documentation on Heading](https://getbootstrap.com/docs/5.3/content/typography/#headings)
> * [Headings](https://getbootstrap.com/docs/5.3/content/typography/#headings)
> * [Display headings](https://getbootstrap.com/docs/5.3/content/typography/#display-headings)

## Properties:
* `html_tag` : The HTML tag to use for the header.
               Defaults to h1 (h1|h2|h3|h4|h5|h6)
* `display`: When you need a heading to stand out, consider using a display
             heading—a larger, slightly more opinionated heading style.
* `utility_classes`: An array of utility classes that can
                    be used to add extra Bootstrap utility classes or custom
                    classes to this component.

## Attributes:
 * `attributes`: Attributes array.

## Slots:
 * `content`: Content text for the heading.

## Examples

**Example #1:** Have a heading for the h1 page title
```
{% include 'varbase_components:heading' with {
    html_tag: 'h1',
    content: title|render|striptags|trim,
    attributes: title_attributes,
    utility_classes: classes
  }
%}
```

**Example #2:** Use for the heading of a block
```
{% include 'varbase_components:heading' with {
    html_tag: heading_tag,
    content: heading_text|render|striptags|trim,
    attributes: []
  }
%}
```

**Example #3:** Use in views title
```
{% embed "varbase_components:heading" with {
  attributes: title_attributes,
  content: label,
  html_tag: 'h2'
} %}
{% endembed %}
```

**Example #4** Have title with utility classes
```
{% include "varbase_components:heading" with {
  attributes: heading_attributes,
  html_tag: 'h2',
  content: heading,
  utility_classes: ['rich-heading', 'mb-2']
} %}
```

**Example #5** Heading for the the alert message
```
	{% if heading|render|striptags|trim is not empty %}
		{% include "varbase_components:heading" with {
			html_tag: 'h4',
			content: heading|render|striptags|trim,
			utility_classes: ['alert-heading']
		} only %}
	{% endif %}
```