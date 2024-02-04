# Accordion item

> ### [Bootstrap documentation on Accordion](https://getbootstrap.com/docs/5.3/components/accordion/)
> * [Example](https://getbootstrap.com/docs/5.3/components/accordion/#example)
> * [Flush](https://getbootstrap.com/docs/5.3/components/accordion/#flush)
> * [Always open](https://getbootstrap.com/docs/5.3/components/accordion/#always-open)
> * [Accessibility](https://getbootstrap.com/docs/5.3/components/accordion/#accessibility)


## Available properties:
* `id`: The target ID for the accordion item.
* `header_tag`: The HTML (h1, h2, h3, h4, h5, or h6) header tag.
* `collapsable`: Have a collapsed or collapsable accordion item.
* `expanded`: Set the accordion item as expanded.
* `utility_classes`: An array of utility classes that can be used to
                    add extra Bootstrap utility classes or custom
                    classes to the root accordion item wrapper.
* `header_utility_classes`: An array of utility classes that can be used to
                    add extra Bootstrap utility classes or custom
                    classes to the accordion header wrapper.
* `body_utility_classes`: An array of utility classes that can be used to
                    add extra Bootstrap utility classes or custom
                    classes to the accordion body wrapper.
* `button_utility_classes`: An array of utility classes that can be used to
                    add extra Bootstrap utility classes or custom
                    classes to the accordion header button.
* `collapse_utility_classes`: An array of utility classes that can be used to
                    add extra Bootstrap utility classes or custom
                    classes to the accordion body collapse wrapper.

## Available attributes:
* `attributes`: HTML attributes for the root accordion item wrapper element.
* `header_attributes`: HTML attributes for the accordion header wrapper element.
* `body_attributes`: HTML attributes for the accordion body wrapper element.
* `button_attributes`: HTML attributes for the accordion header button element.
* `collapse_attributes`: HTML attributes for the accordion collapse wrapper element for the accordion body.

## Available slots:
* `header`: Accordion header - The heading part of the accordion item.
* `body`: Accordion body - The content body part of the accordion item.


## Examples:

### Example #1
Accordion item 1 with accordion test.
```
  <div id="accordion-test" class="accordion">
      {% include "varbase_components:accordion-item" with {
        item_id: 'item-1',
        accordion_id: 'accordion-test',
        header_tag: 'h4',
        collapsable: true,
        expanded: false,
        header: 'Accordion header',
        body: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc non sapien lacinia, pellentesque elit sit amet, tempor orci.',
      } only %}
  </div>
```

### Example #2
Expanded 2nd accordion item.
```
  <div id="accordion-512" class="accordion">
      {% include "varbase_components:accordion-item" with {
        item_id: 'item-34',
        accordion_id: 'accordion-512',
        header_tag: 'h4',
        collapsable: true,
        expanded: false,
        header: 'Accordion header',
        body: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc non sapien lacinia, pellentesque elit sit amet, tempor orci.',
      } only %}

      {% include "varbase_components:accordion-item" with {
        item_id: 'item-802',
        accordion_id: 'accordion-512',
        header_tag: 'h4',
        collapsable: true,
        expanded: true,
        header: 'Accordion header',
        body: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nunc non sapien lacinia, pellentesque elit sit amet, tempor orci.',
      } only %}
  </div>
```

### Example #3
Looping over the items array with list of accordion items.
```
  <div class="accordion">
    {% for item in items %}
      {% include "varbase_components:accordion-item" with {
        item_id: item.id,
        accordion_id: accordion_id,
        header_tag: header_tag,
        collapsable: item.collapsable,
        expanded: loop.index == expanded_item_index ? true : false,
        header: item.header,
        body: item.body,
      } only %}
    {% endfor %}
  </div>
```