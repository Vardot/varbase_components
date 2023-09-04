# Table

The Table component with support for Bootstrap.

Due to the widespread use of `<table>` elements across third-party widgets like calendars
and date pickers, Bootstrap’s tables are opt-in. Add the base class .table to any `<table>`,
then extend with our optional modifier classes or custom styles. All table styles are not
inherited in Bootstrap, meaning any nested tables can be styled independent from the parent.

> ### [Bootstrap ~5.3.0 Documentation on Tables](https://getbootstrap.com/docs/5.3/content/tables/)
> * [Overview](https://getbootstrap.com/docs/5.3/content/tables/#overview)
> * [Variants](https://getbootstrap.com/docs/5.3/content/tables/#variants)
> * [Accented tables](https://getbootstrap.com/docs/5.3/content/tables/#accented-tables)
> * [Striped rows](https://getbootstrap.com/docs/5.3/content/tables/#striped-rows)
> * [Striped columns](https://getbootstrap.com/docs/5.3/content/tables/#striped-columns)
> * [Hoverable rows](https://getbootstrap.com/docs/5.3/content/tables/#hoverable-rows)
> * [Active tables](https://getbootstrap.com/docs/5.3/content/tables/#active-tables)
> * [How do the variants and accented tables work?](https://getbootstrap.com/docs/5.3/content/tables/#how-do-the-variants-and-accented-tables-work)
> * [Table borders](https://getbootstrap.com/docs/5.3/content/tables/#table-borders)
> * [Bordered tables](https://getbootstrap.com/docs/5.3/content/tables/#bordered-tables)
> * [Tables without borders](https://getbootstrap.com/docs/5.3/content/tables/#tables-without-borders)
> * [Small tables](https://getbootstrap.com/docs/5.3/content/tables/#small-tables)
> * [Table group dividers](https://getbootstrap.com/docs/5.3/content/tables/#table-group-dividers)
> * [Vertical alignment](https://getbootstrap.com/docs/5.3/content/tables/#vertical-alignment)
> * [Nesting](https://getbootstrap.com/docs/5.3/content/tables/#nesting)
> * [Table head](https://getbootstrap.com/docs/5.3/content/tables/#table-head)
> * [Table foot](https://getbootstrap.com/docs/5.3/content/tables/#table-foot)
> * [Captions](https://getbootstrap.com/docs/5.3/content/tables/#captions)
> * [Responsive tables](https://getbootstrap.com/docs/5.3/content/tables/#responsive-tables)
> * [Breakpoint specific](https://getbootstrap.com/docs/5.3/content/tables/#breakpoint-specific)

### Properties:
* `caption`: A localized string for the `<caption>` tag.
* `colgroups`: Column groups which contains attributes
* `header`: Table header cells which contains (tag, attributes, content, field, sort)
* `sticky`: A flag indicating whether to use a sticky table header.
* `rows`: Table rows, which each row contains (attributes, data, no_striping, cells).
*         Each cell contains (tag, attributes, content, active_table_sort)
* `footer`: Table footer rows, in the same format as the rows variable.
* `empty`: The message to display in an extra row if table does not have any rows.
* `no_striping`: A boolean indicating that the row should receive no striping.
* `header_columns`: The number of columns in the header.
* `utility_classes`: An array of utility classes. Use to add extra Bootstrap utility classes or
                     custom CSS classes over to the table element.
* `thead_utility_classes`: An array of utility classes. Use to add extra Bootstrap utility classes or
                           custom CSS classes over to the head of the table element.
* `tbody_utility_classes`: An array of utility classes. Use to add extra Bootstrap utility classes or
                           custom CSS classes over to the body of the table element.
* `tfoot_utility_classes`: An array of utility classes. Use to add extra Bootstrap utility classes or
                           custom CSS classes over to the footer of the table element.                     

### Attributes:
* `attributes`: HTML attributes to apply to the `<table>` tag.
* `thead_attributes`: HTML attributes to apply to the `<thead>` tag.
* `tbody_attributes`: HTML attributes to apply to the `<tbody>` tag.
* `tfoot_attributes`: HTML attributes to apply to the `<tfoot>` tag.
### Slots:
* N/A
