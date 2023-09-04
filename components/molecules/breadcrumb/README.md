# Breadcrumb

Breadcrumb trail items that indicate the current page’s location within a navigational hierarchy that automatically adds separators via CSS.

> ### [Bootstrap ~5.3.0 documentation on Breadcrumb](https://getbootstrap.com/docs/5.3/components/breadcrumb)
> * [Unordered list with linked list items](https://getbootstrap.com/docs/5.3/components/breadcrumb/#example)
> * [Dividers](https://getbootstrap.com/docs/5.3/components/breadcrumb/#dividers)
> * [Accessibility: ARIA Authoring Practices Guide breadcrumb pattern](https://www.w3.org/WAI/ARIA/apg/patterns/breadcrumb/)





### Examples:

**Example #1** Used in the `breadcrumb.html.twig` template
```
{% include 'varbase_components:breadcrumb' with {
  breadcrumb: breadcrumb,
} %}
```

**Example #2** Change the style of divider
```
{% include 'varbase_components:breadcrumb' with {
  breadcrumb: breadcrumb,
  divider: '>'
} %}
```

**Example #3** Have more spacing
```
{% include 'varbase_components:breadcrumb' with {
  breadcrumb: breadcrumb,
  utility_classes: ['m-sm-2', 'm-md-3', 'm-xxl-5']
} %}
```