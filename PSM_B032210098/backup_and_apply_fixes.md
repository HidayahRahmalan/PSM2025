# 🛠️ PS4 Rental System Database Fix Instructions

## ⚠️ **IMPORTANT: Create Backup First!**

Before applying any fixes, **ALWAYS** backup your database:

### 1. **Create Database Backup**
```bash
# Using phpMyAdmin: Export → Custom → Save as file
# OR using command line:
mysqldump -u root -p ps4_db > ps4_db_backup_$(date +%Y%m%d_%H%M%S).sql
```

### 2. **Apply the Fixes**

**Option A: Using phpMyAdmin**
1. Open phpMyAdmin
2. Select `ps4_db` database
3. Click "SQL" tab
4. Copy and paste the entire content of `ps4_db_fixes.sql`
5. Click "Go" to execute

**Option B: Using Command Line**
```bash
mysql -u root -p ps4_db < ps4_db_fixes.sql
```

### 3. **Verify Fixes Applied**

Run these verification queries in phpMyAdmin:

```sql
-- Check payment_ID constraint is fixed
DESCRIBE `rentals`;
-- Should show: payment_ID | varchar(10) | YES | | NULL |

-- Check all consoles exist
SELECT * FROM `consoles`;
-- Should show CONS0001, CONS0002, CONS0003

-- Check foreign keys were added
SELECT 
  TABLE_NAME, 
  COLUMN_NAME, 
  CONSTRAINT_NAME, 
  REFERENCED_TABLE_NAME 
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'ps4_db' 
  AND TABLE_NAME = 'rentals' 
  AND REFERENCED_TABLE_NAME IS NOT NULL;
```

### 4. **Test Booking System**

1. Go to your booking page: `customer/new_booking.php`
2. Try creating a test booking
3. Should redirect to payment page successfully
4. Check if booking appears in database with `rental_status = 'pending_payment'`

## 🚨 **If Something Goes Wrong (Rollback Plan)**

If the fixes cause issues:

### **Restore from Backup**
```bash
# Drop current database and restore from backup
mysql -u root -p -e "DROP DATABASE ps4_db;"
mysql -u root -p -e "CREATE DATABASE ps4_db;"
mysql -u root -p ps4_db < your_backup_file.sql
```

### **Or Reverse Individual Changes**
```sql
-- If you need to reverse payment_ID change (NOT RECOMMENDED)
ALTER TABLE `rentals` MODIFY `payment_ID` varchar(10) NOT NULL;

-- Remove foreign keys if needed
ALTER TABLE `rentals` DROP FOREIGN KEY `rentals_ibfk_2`;
ALTER TABLE `rentals` DROP FOREIGN KEY `rentals_ibfk_3`;
ALTER TABLE `rentals` DROP FOREIGN KEY `rentals_ibfk_4`;
```

## ✅ **Expected Results After Fixes**

1. **Booking Creation**: Should work without errors
2. **Payment Flow**: Should redirect to payment page correctly  
3. **Error Messages**: Should show specific errors instead of generic failures
4. **Data Integrity**: Foreign key constraints prevent invalid data
5. **Performance**: Indexes improve query speed

## 📞 **Need Help?**

If you encounter any issues:
1. Check the error log for specific error messages
2. Verify all SQL commands executed successfully
3. Ensure your backup is safe before making changes
4. Test with a simple booking first

**Critical Files Modified:**
- ✅ `ps4_db_fixes.sql` - Database fixes
- ✅ `customer/new_booking.php` - Enhanced error reporting
- ✅ `css/style.css` - Added missing text-purple class

Your booking system should now work perfectly! 🎮