# Item Grade System Implementation

## Overview
The ItemLog system has been enhanced to support two different item grades: **New** and **Used**. Each grade has different tracking mechanisms and UI interfaces.

## Database Changes

### New Fields Added:
- `items.grade` - ENUM('New', 'Used') - Determines item type
- `items.weight` - DECIMAL(10,2) - For tracking Used items by weight (kg)
- `borrowings.weight_borrowed` - DECIMAL(10,2) - For tracking borrowed weight

### Migration Required:
Run the migration script to update your existing database:
```sql
-- Execute database/migration.sql
```

## Feature Details

### New Items
- **Tracking**: By quantity (pieces/units)
- **UI Fields**: Item name, category, quantity, condition, location, image
- **Borrowing**: Select quantity to borrow
- **Display**: Shows quantity with color-coded badges

### Used Items
- **Tracking**: By weight (kilograms)
- **UI Fields**: Item name, category, weight, condition, location, image
- **Borrowing**: Enter weight to borrow
- **Display**: Shows weight in kg with color-coded badges

## UI Changes

### Items Management (items.php)
- **Grade Selection**: Dropdown to choose New or Used
- **Dynamic Forms**: Different fields shown based on grade selection
- **Table Display**: Shows Grade column and appropriate Quantity/Weight values
- **Search/Filter**: Added Grade filter option
- **Bulk Operations**: Support for both grade types

### Borrowing System (borrow.php)
- **Dynamic Form**: Shows quantity field for New items, weight field for Used items
- **Validation**: Appropriate validation for each grade type
- **QR Scanner**: Updated to handle both grade types

### Return System (return.php)
- **Smart Returns**: Automatically handles quantity vs weight returns
- **Logging**: Detailed logging for both grade types

### Dashboard (dashboard.php)
- **Low Stock Alerts**: Different thresholds for New (< 5) vs Used (< 10 kg)
- **Statistics**: Updated to handle both grade types
- **Display**: Shows appropriate values for each grade

## JavaScript Functions

### New Functions Added:
- `toggleGradeFields()` - Shows/hides appropriate fields in Add Item form
- `toggleEditGradeFields()` - Shows/hides appropriate fields in Edit Item form
- `updateBorrowOptions()` - Updates borrow form based on selected item grade

## Usage Examples

### Adding a New Item:
1. Select "New" grade
2. Fill in: name, category, quantity, condition, location, image
3. Save item

### Adding a Used Item:
1. Select "Used" grade
2. Fill in: name, category, weight, condition, location, image
3. Save item

### Borrowing:
1. Select item from dropdown (shows grade and available quantity/weight)
2. Form automatically shows appropriate field (quantity for New, weight for Used)
3. Enter purpose and submit

### Filtering:
- Use the Grade filter in items.php to view only New or Used items
- Search functionality includes grade in search terms

## Backward Compatibility
- Existing items are automatically set to "New" grade
- Existing borrowings are updated with weight_borrowed = 0
- All existing functionality remains intact

## Notes
- Used items cannot have quantity > 0
- New items cannot have weight > 0
- The system enforces these rules in the UI and database
- All logging and activity tracking has been updated to handle both grade types 