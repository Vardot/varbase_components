# Heading

All HTML headings, `<h1>` through `<h6>`, are available.


### Bootstrap Documentation
https://getbootstrap.com/docs/5.3/content/typography/#headings
https://getbootstrap.com/docs/5.3/content/typography/#display-headings

## Properties:

* `html_tag` : The HTML tag to use for the header.
             Defaults to h1 (h1 | h2 | h3 | h4 | h5 | h6)
* `display`: When you need a heading to stand out, consider using a display
              `heading—a` larger, slightly more opinionated heading style.
* `attributes`: Attributes array.
* `utility_classe`: This property contains an array of utility classes that can
                    be used to add extra Bootstrap utility classes or custom
                    classes to this component.

#### HTML Tag (`html_tag`)
Heading HTML tag (h1, h2, h3, h4, h5, h6)

#### Display (`display`):
When you need a heading to stand out, consider using a display
heading—a larger, slightly more opinionated heading style.
optional values are: ( display-1, display-2, display-3, display-4, display-5, display-6)
https://getbootstrap.com/docs/5.3/content/typography/#display-headings

#### Utility Classes (`utility_classes`):
This property contains an array of utility classes that can be used to
add extra Bootstrap utility classes or custom classes to this component.

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