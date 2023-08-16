# Button

Use Bootstrap’s custom button styles for actions in forms, dialogs, and more with support for multiple sizes, states, and more.


## Bootstrap Documentation
https://getbootstrap.com/docs/5.3/components/buttons/

## Available Properties:
* `html_tag`: The HTML tag to use for the button (button | a). Defaults to `button`.
* `link`: Optional and only when having HTML tag as anchor.
* `color`: Bootstrap includes several predefined button styles, each serving its own
          semantic purpose, with a few extras thrown in for more control.
          (primary | secondary | success | danger | warning | info | dark | light | link)
* `outline`: (true|false) In need of a button, but not the hefty background colors they bring?
              Replace the default modifier classes with the `.btn-outline-*` ones to remove all
              background images and colors on any button.
* `size`: (btn-sm | btn-lg) Bootstrap button size
* `disabled`: (true|false) Disabled button
* `utility_classes`: An array of utility classes.

## Available Slots:
* `content`: The content for the button

### Examples
**Example #1:** Primary button
```
  {% include 'varbase_components:button' with {
    html_tag: 'button',
    color: 'primary',
    content: 'Login'
  } %}
```

**Example #2:** Anchor button
```
  {% include 'varbase_components:button' with {
    html_tag: 'a',
    link: '/blog/test-blog1',
    size: 'btn-sm',
    content: 'Read more',
    outline: true
  } %}
```

**Example #3:** Disabled Anchor button
```
  {% include 'varbase_components:button' with {
    html_tag: 'a',
    color: 'primary',
    link: '#',
    content: 'Read more',
    disabled: true,
  } %}
```
