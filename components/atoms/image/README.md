# Image

Image component with responsive behavior for the system image.

Images in Bootstrap are made responsive with `.img-fluid`. This applies
 `max-width: 100%;` and `height: auto;` to the image so that it scales
 with the parent width.

> ## [Bootstrap ~5.3.0 Documentation on Images](https://getbootstrap.com/docs/5.3/content/images)
> * [Images](https://getbootstrap.com/docs/5.3/content/images)
> * [Responsive Images](https://getbootstrap.com/docs/5.3/content/images/#responsive-images)


## Properties:
* `align`: (optional) Align images with the helper float classes or text alignment classes.
            block-level images can be centered using the .mx-auto margin utility class.
            options (`float-start`, `mx-auto d-block`, `float-end`)
* `responsive`: (optional) Images in Bootstrap are made responsive with `.img-fluid`.
                This applies max-width with 100% and height with auto to the image so
                that it scales with the parent width.
* `thumbnails`: (optional)(true|false) In addition to Bootstrap border-radius utilities, you can
                use `.img-thumbnail` to give an image a rounded 1px border appearance.
* `rounded`: (optional)(true/false) Rounded image
* `utility_classes`: (optional) An array of utility classes. Use to add extra Bootstrap utility classes or custom CSS classes over to this to this component.
 *
## Attributes:
* `attributes`: HTML attributes for the img tag.

## Slots:
* N/A

### Examples

**Example #1:** Default system image
```
{% include 'varbase_components:image' %}
```

**Example #2:** Use the Image component with Responsive image
```
{% include 'varbase_components:image' with {
    responsive: true
  }
%}
```

**Example #3:** Use the Image component with align center, rounded, and thumbnails 
```
{% include 'varbase_components:image' with {
    align: 'center',
    responsive: true,
    rounded: true,
    thumbnails: true
  }
%}
```

**Example #3:** Use the Image component with align center, rounded, and thumbnails 
```
{% include 'varbase_components:image' with {
    align: 'center',
    responsive: true,
    rounded: true,
    thumbnails: true
    attributes: ["src": "https://www.vardot.com/sites/default/files/styles/de2e_standard/public/images/2019-12/nik-macmillan-yxemfqipr_e-unsplash-04_3.jpg"]
  }
%}
```