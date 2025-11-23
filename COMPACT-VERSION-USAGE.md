# Compact Scholarship Cards with Bootstrap Modal

This plugin now includes a **compact version** of the scholarship cards that displays essential information on the card while moving detailed information into a Bootstrap modal.

## Features

### Essential Information on Card
The compact cards display only the most important information directly:
- **Title** - Scholarship name
- **Deadline** - Application deadline
- **Amount** - Maximum award amount
- **Category** - Scholarship category badge
- **Eligibility** - Who can apply
- **Location** - Geographic location
- **Number of Awards** - How many scholarships available

### Detailed Information in Modal
Additional details are shown in a Bootstrap modal when clicking "View Details":
- **Minimum CGPA** - Required GPA
- **Affiliation** - Required affiliations
- **Age Requirement** - Age restrictions
- **College Program** - Required program
- **License Types** - Accepted aviation licenses

### Dual Action Buttons
Each card includes two buttons:
1. **View Details** - Opens Bootstrap modal with full information
2. **Apply Now** - Direct link to application (opens in new tab)

## Usage

### Shortcode

Use the compact version with this shortcode:

```
[recent_scholarships_compact count="6"]
```

**Parameters:**
- `count` - Number of scholarships to display (default: 6)

### Original Version

The original version is still available and unchanged:

```
[recent_scholarships count="6"]
```

## Styling

The compact version maintains the same Apple-inspired design language:
- Clean, modern cards with subtle shadows
- Smooth hover animations
- Responsive grid layout
- Support for dark mode
- Accessibility features

The Bootstrap modal is styled to match:
- Rounded corners (18px border-radius)
- Smooth animations
- Clean typography
- Consistent color scheme using GeneratePress theme colors

## Technical Details

### Files Added

1. **helpers-template-compact.php** - Compact card rendering functions
2. **frontend-cards-compact.css** - Compact card styles and Bootstrap modal overrides

### Files Modified

1. **aviation-scholarships.php** - Registers compact shortcode
2. **shortcodes.php** - Adds compact shortcode function
3. **class-assets.php** - Conditionally loads Bootstrap and compact CSS

### Dependencies

The compact version automatically loads:
- Bootstrap 5.3.2 CSS (from CDN)
- Bootstrap 5.3.2 JS Bundle (from CDN)
- Custom compact card styles

Dependencies are only loaded when the compact shortcode is used on a page.

## Browser Compatibility

The compact version works in all modern browsers:
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Responsive Design

The compact cards are fully responsive:
- Desktop: Multi-column grid
- Tablet: Adaptive columns
- Mobile: Single column stacked layout
- Modal: Optimized for all screen sizes

## Accessibility

Both buttons and modals include:
- Proper ARIA labels
- Keyboard navigation support
- Focus indicators
- Screen reader compatibility

## Example Integration

Add the shortcode to any WordPress page or post:

```
<h2>Available Aviation Scholarships</h2>
[recent_scholarships_compact count="9"]
```

Or use in a custom template:

```php
<?php
echo do_shortcode('[recent_scholarships_compact count="6"]');
?>
```

## Coexistence with Original Version

Both versions can coexist on the same site:
- Use different shortcodes on different pages
- Original version has more info upfront
- Compact version is cleaner for browsing many scholarships
- Both maintain consistent styling

## Support

For questions or issues, refer to the main plugin documentation.
