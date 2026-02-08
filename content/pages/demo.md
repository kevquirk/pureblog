---
title: Demo
slug: demo
status: published
description: A demo page showing common Markdown elements.
include_in_nav: true
---

This demo not only shows you how to format commonly used elements in pages and posts, it will also show you how to use them in the markdown editor. There are some elements that markdown doesn't support, where this occurs, HTML is used instead.

## Basic Typography

All the typography in Pure Blog uses `rem` for sizing. This means that accessibility is maintained for those who change their browser font size. The `body` element has a size of `1.15rem`  which makes all the standard font sizes slightly larger. This equates to `18.4px` for paragraph text, instead of the standard `16px`.

The heading elements also have an increased top margin in order to break blocks of text up better, which improves readability.

# Heading 1

## Heading 2

### Heading 3

#### Heading 4

##### Heading 5

###### Heading 6

```
# Heading 1
## Heading 2
### Heading 3
#### Heading 4
##### Heading 5
###### Heading 6
```

### Links & Buttons

Links are formatted very simply. They use the `accent-color` CSS variable and are underlined. There is a `:hover` effect that removes the underline.

Buttons use the same `accent-color` CSS variable for their colour. When hovering, there is a different colour applied.

[I'm a hyperlink](https://example.com)

<button>I'm a button</button>

<a class="button" href="https://example.com">I'm a button with a link</a>

```
[I'm a hyperlink](https://example.com)

<button>I'm a button</button>

<a class="button" href="https://example.com">I'm a button with a link</a>
```

## Other typography elements

There are a number of other typography elements that you can use. Some of the common ones are:

* All the standard stuff, like **bold**, _italic_ and <u>underlined</u> text.
* <mark>Highlighting text</mark> using the `mark` element.
* Adding `inline code` using the `code` element.
* Displaying keyboard commands like <kbd>ALT+F4</kbd> using the `kbd` element.

```
**Bold text**
_Italic text_ or *Italic text*
<u>Underlined text</u>
<mark>Highlighted text</mark>
`Inline code`
<kbd>Alt+F4</kbd>
```

### Lists

We all love a good list, right? I know my wife does!

* Item 1
* Item 2
* Item 3

1. Do this thing
2. Do that thing
3. Do the other thing

```
# Bulleted list
* Item 1
* Item 2
* Item 3

# Numbered list
1. Do this thing
2. Do that thing
3. Do the other thing
```
### Blockquotes

Sometimes you may want to quote someone else in your HTML. For this we use the `blockquote` element. Here's what a quote looks like in Pure Blog:

> Friends don’t spy; true friendship is about privacy, too.
>
> <cite>– Stephen King</cite>

```
> Friends don’t spy; true friendship is about privacy, too.
>
> <cite>– Stephen King</cite>
```

### Code blocks

Code blocks are different from the inline `code` element. Code blocks are used when you want to display a block of code, like this:

```
body {
  color: var(--text);
  background: var(--bg);
  font-size: 1.15rem;
  line-height: 1.5;
  margin: 0;
}
```

````
```
body {
  color: var(--text);
  background: var(--bg);
  font-size: 1.15rem;
  line-height: 1.5;
  margin: 0;
}
```
````

### Notice box

<p class="notice">This is a notice box. It's useful for calling out snippets of information. Cool, huh?</p>

```
<p class="notice">This is a notice box. It's useful for calling out snippets of information. Cool, huh?</p>
```

## Images

Images within your main content are always full width and have rounded corners to them. The `figcaption` element is also formatted in Pure Blog. Here are examples of images with and without a caption:

![A dog at an iPad](/content/images/demo/dog-ipad.jpg)

![A black swan](/content/images/demo/goose.jpg)
*This is a black swan*

```
# Standard image
![A dog at an iPad](/content/images/demo/dog-ipad.jpg)

# Image with a caption 
![A black swan](/content/images/demo/goose.jpg)
*This is a black swan*
```

## Details & Accordions

Details elements are cool to play with. They're especially useful when it comes to things like FAQ pages. Many people invoke JavaScript, or `div` for life when they use accordions. I don't really understand why that is when it's available in plain old HTML:

<details>
  <summary>Spoiler alert!</summary>
  <p>You smell. 🙂</p>
</details>

```
<details>
  <summary>Spoiler alert!</summary>
  <p>You smell. 🙂</p>
</details>
```

The `details` element can also be made to work as an accordion where one element opens and others in the list close. Like this:

<details name="faq" >
  <summary>FAQ 1</summary>
  <p>Can you smell that?</p>
</details>

<details name="faq" >
  <summary>FAQ 2</summary>
  <p>Something really stinks.</p>
</details>

<details name="faq" >
  <summary>FAQ 3</summary>
  <p>Oh, it's you. 🙂</p>
</details>

To do this, you need to group the `details` elements together with a `name`, like this:

```
<details name="faq" >
  <summary>FAQ 1</summary>
  <p>Can you smell that?</p>
</details>

<details name="faq" >
  <summary>FAQ 2</summary>
  <p>Something really stinks.</p>
</details>

<details name="faq" >
  <summary>FAQ 3</summary>
  <p>Oh, it's you. 🙂</p>
</details>
```

## Tables

Like lists, sometimes you may need to add a table to your webpage. In Pure Blog tables automatically highlight every other row to make reading easier. Table header text is also bold. Here's what they look like:

| Name  |  Number |
|---|---|
| Jackie  | 012345  |
| Lucy  | 112346  |
| David  | 493029  |
| Kerry  |  395499 |
|  Steve | 002458  |

```
<table>
  <thead>
    <tr>
      <th>Name</th>
      <th>Number</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Jackie</td>
      <td>012345</td>
    </tr>
    <tr>
      <td>Lucy</td>
      <td>112346</td>
    </tr>
    <tr>
      <td>David</td>
      <td>493029</td>
    </tr>
    <tr>
      <td>Kerry</td>
      <td>395499</td>
    </tr>
    <tr>
      <td>Steve</td>
      <td>002458</td>
    </tr>
  </tbody>
</table>
```

## Forms

Forms are useful for all kinds of things on webpages. Contact forms, newsletter sign ups etc. Forms also look pretty good on Pure Blog:

<form>
  <p class="notice">This is just a test form. It doesn't do anything.</p>
  <p>
  <select>
    <option selected="selected" value="1">Title</option>
    <option value="2">Mr</option>
    <option value="3">Miss</option>
    <option value="4">Mrs</option>
    <option value="5">Other</option>
  </select>
  </p>
  <p>
  <label>First name</label>
  <input type="text" name="first_name">
  </p>
  <p>
  <label>Surname</label>
  <input type="text" name="surname">
  </p>
  <p>
  <label>Email</label>
  <input type="email" name="email" required="">
  </p>
  <p>
  <label>Enquiry type:</label>
  <label><input checked="checked" name="type" type="radio" value="sales" /> Sales</label> 
  <label><input name="type" type="radio" value="support" /> Support</label> 
  <label><input name="type" type="radio" value="billing" /> Billing</label>
  </p>
  <p>
  <label>Message</label>
  <textarea rows="6"></textarea>
  </p>
  <p>
  <label for="cars">Choose a car:</label>
  <select name="cars" id="cars" multiple>
  <option value="volvo">Volvo</option>
  <option value="saab">Saab</option>
  <option value="opel">Opel</option>
  <option value="audi">Audi</option>
  </select>
  </p>
  <p>
  <label>
  <input type="checkbox" id="checkbox" value="terms">
  I agree to the <a href="#">terms and conditions</a>
  </label>
  </p>

  <button>Send</button>
  <button type="reset">Reset</button>
  <button disabled="disabled">Disabled</button>
</form>

```HTML
<form>
  <p><select>
    <option selected="selected" value="1">Title</option>
    <option value="2">Mr</option>
    <option value="3">Miss</option>
    <option value="4">Mrs</option>
    <option value="5">Other</option>
  </select></p>

  <p>
  <label>First name</label>
  <input type="text" name="first_name">
  </p>

  <p>
  <label>Surname</label>
  <input type="text" name="surname">
  </p>

  <p>
  <label>Email</label>
  <input type="email" name="email" required="">
  </p>

  <p>
  <label>Enquiry type:</label>
  <label><input checked="checked" name="type" type="radio" value="sales" /> Sales</label> 
  <label><input name="type" type="radio" value="support" /> Support</label> 
  <label><input name="type" type="radio" value="billing" /> Billing</label>
  </p>

  <p>
  <label>Message</label>
  <textarea rows="6"></textarea>
  </p>

  <p>
  <label for="cars">Choose a car:</label>
  <select name="cars" id="cars" multiple>
  <option value="volvo">Volvo</option>
  <option value="saab">Saab</option>
  <option value="opel">Opel</option>
  <option value="audi">Audi</option>
  </select>
  </p>

  <p>
  <label>
  <input type="checkbox" id="checkbox" value="terms">
  I agree to the <a href="#">terms and conditions</a>
  </label>
  </p>

  <button>Send</button>
  <button type="reset">Reset</button>
  <button disabled="disabled">Disabled</button>
</form>
```
