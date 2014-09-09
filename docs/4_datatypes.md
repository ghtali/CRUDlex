Data Types
==========

This is a comprehensive list of all supported data types, their parameters and
the related MySQL-types.

## Text

```yml
type: text
```

A single text line, no further parameters. Related MySQL-types:
- CHAR
- VARCHAR (recommended)
- TINYTEXT
- TEXT
- MEDIUMTEXT
- LONGTEXT

## Multiline

```yml
type: multiline
```

A multi text line allowing linebreaks, no further parameters. Related MySQL-types:
- CHAR
- VARCHAR (recommended)
- TINYTEXT
- TEXT
- MEDIUMTEXT
- LONGTEXT

If the field is shown in the list view and the value is longer than 27
characters, the rest is cut and replaced with three dots. The full text is still
available as tooltip in the list view though. Example with 50 characters:

"Lorem ipsum dolor sit amet, consetetur sadipscing"

Would be shown in the list view as:

"Lorem ipsum dolor sit amet,..."

## Url

```yml
type: url
```

A single text line representing an URL, no further parameters. Related MySQL-types:
- CHAR
- VARCHAR (recommended)
- TINYTEXT
- TEXT
- MEDIUMTEXT
- LONGTEXT

The only difference to the type "text" is that url fields are clickable in the
list and show view. They are shortened in the list view to their base name in
order to save space. A value of "http://www.foo.com/bar.txt" would lead to
"http://www.foo.com/bar.txt" on click and is shortened to "bar.txt" in the list.

## Int

```yml
type: int
```

An integer, no further parameters. Related MySQL-types:
- TINYINT
- SMALLINT
- MEDIUMINT
- INT (recommended)
- BIGINT

## Boolean

```yml
type: bool
```

A boolean value, either true or false, no further parameters. Related MySQL-type:
- TINYINT

Saved as 0 (false) or 1 (true).

## Date

```yml
type: date
```

A date value without time, no further parameters. Related MySQL-types:
- DATE
- DATETIME (recommended)
- TIMESTAMP

## Datetime

```yml
type: datetime
```

A date value with time, no further parameters. Related MySQL-type:
- DATETIME (recommended)
- TIMESTAMP

## Set

```yml
type: set
setitems: [red, green, blue]
```

A fixed set of elements to be chosen from, stored as text. Related MySQL-types:
- CHAR
- VARCHAR (recommended)
- TINYTEXT
- TEXT
- MEDIUMTEXT
- LONGTEXT

In this example, the user has the choice between the three colors "red",
"green" and "blue".

## Reference

```yml
type: reference
reference:
  table: otherTable
  nameField: otherName
  entity: otherEntity
```

A fixed set of elements to be chosen from, stored as text. Related MySQL-type:
- INT

This is the 1-side of a one-to-many relation. In order to display a proper
selection UI and represent the the value from the other table, a few more fields
are needed. Those are the table telling CRUDlex where to look for the,
representation, the nameField describing which field to use from the other table
to display the selected value and last, the referenced entity.

Think about a book in a library. The library is stored in the table "lib" and
has a field "name". A book belongs to a library, so it has an integer field
"library" referencing ids of libraries. Here is the needed yml for this
book-library relationship:

```yml
library:
    table: lib
    label: Library
    fields:
        name:
            type: text
book:
    table: book
    label: Book
    fields:
        title:
            type: text
        author:
            type: text
        library:
            type: reference
            reference:
              table: lib
              nameField: name
              entity: library
```

Don't forget to set the MySQL foreign key.

```sql
ALTER TABLE `book`
ADD CONSTRAINT `book_ibfk_1` FOREIGN KEY (`library`) REFERENCES `lib` (`id`);
```

If a book still references a library, CRUDlex refuses to delete the library if
you try.

---

Previous: [Data Structure Definition](3_datastructures.md)

Next: [Constraints](5_constraints.md)