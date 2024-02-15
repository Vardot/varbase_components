# Text Card

Text card component and pattern.

### Properties:
* `style_size`: Card style size (xsmall|small|medium|large|xlarge).
* `card_border`: Card Boarder (true|false)
* `padded`: Add a default padding to the card. (true|false)
* `equal_height`: Equal height (true|false)
* `anchor_all`: Anchor All (true|false)
* `card_attributes`: Drupal attributes for featured card wrapper.
* `content_attributes`: Drupal attributes for card content slot region.
* `utility_classes: Use to add extra Bootstrap utility classes for the main Card wrapper. E.g. `mb-3 shadow-lg` ( Do not add card)
* `content_utility_classes`: Use to add extra Bootstrap utility classes for the Card Content region wrapper. E.g. `w-75 mb-3 overflow-y-hidden`  ( Do not add card-body)

### Slots:
* `content`: Card Content slot region.

### Options to custom:
- Full clone/copy the **Text Card** component and [customize it in a custom theme](https://docs.varbase.vardot.com/v/10.0.x/developers/theme-development-with-varbase/customize-a-varbase-sdc-component-in-a-custom-theme)
- Minimal copy of parts of the component and use for targeted selected entity type or bundle, and have the custom changes with styles(`css`) and scripts(`js`), or even with custom props and slots.
```
{% include 'varbase_components:card-text' with {
  style_size: small,
  card_border: false,
  padded: false,
  equal_height: false,
  anchor_all: false,
  utility_classes: [],
  content_utility_classes: [],
  content: content,
} only %}
```
