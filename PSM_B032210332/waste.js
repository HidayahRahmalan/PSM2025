// real_ka_ni.js

const express = require('express');
const cors = require('cors');
const mysql = require('mysql2');
const path = require('path');
const fs = require('fs');
const bcrypt = require('bcrypt');
const multer = require('multer'); // multer was missing from the original requires
const app = express();
const port = 3000;

app.use(cors());
app.use(express.json());
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// MySQL connection pool
const pool = mysql.createPool({
    host: '127.0.0.1',          // Standard loopback IP address for localhost
    user: 'B032210332',
    database: 'psm_b032210332',
    password: 'fxiryz07',
    port: 3306,                 // The default port for MySQL
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
}).promise(); // Use the promise-based wrapper for modern async/await syntax

// Export the promise-based pool to be used in other parts of your application
module.exports = pool;

// =======================
//   User Endpoints
// =======================

function generateVerificationCode() {
  return Math.floor(100000 + Math.random() * 900000).toString();
}

app.post('/add-user', async (req, res) => {
  console.log("Received request body for registration:", req.body);
  const { user_name, user_email, user_phonenumber, username, password, user_role, user_position, user_department, specialist } = req.body;
  if (!user_name || !user_email || !username || !password || !user_role || !user_position || !user_department) { return res.status(400).json({ error: 'Missing required user information.' }); }
  
  try {
    const [phoneCheckResult] = await pool.query('SELECT 1 FROM user_censei WHERE user_phonenumber = ?', [user_phonenumber]);
    if (phoneCheckResult.length > 0) { return res.status(409).json({ error: 'Phone number is already registered.' }); }

    const [departmentResult] = await pool.query('SELECT departmentid FROM department WHERE department_name = ?', [user_department]);
    if (departmentResult.length === 0) { return res.status(400).json({ error: 'Invalid department selected. Please choose a valid department.' }); }
    
    const departmentId = departmentResult[0].departmentid;
    const hashedPassword = await bcrypt.hash(password, 10);
    const user_code = generateVerificationCode();
    const user_status = 'unverified';

    const query = `INSERT INTO user_censei (user_name, user_email, user_phonenumber, username, user_password, user_role, user_position, departmentid, specialist, user_code, user_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);`;
    const values = [ user_name, user_email, user_phonenumber, username, hashedPassword, user_role, user_position, departmentId, specialist || null, user_code, user_status ];
    
    const [result] = await pool.query(query, values);
    const newUserId = result.insertId;

    const [newUser] = await pool.query('SELECT userid, user_name, user_email, username, user_role, user_position FROM user_censei WHERE userid = ?', [newUserId]);

    console.log(`(Simulated Email) Verification code for ${user_email}: ${user_code}`);
    res.status(201).json({ message: 'User registered successfully. A verification code has been sent to your email.', user: newUser[0] });
  } catch (err) {
    console.error('Database Insert or Email Error:', err);
    if (err.code === 'ER_DUP_ENTRY') {
        if (err.message.includes('user_censei_username_key')) { return res.status(409).json({ error: 'Username already exists.' }); }
        if (err.message.includes('user_censei_user_email_key')) { return res.status(409).json({ error: 'Email already exists.' }); }
        return res.status(409).json({ error: 'A unique field (username, email) already exists.' });
    }
    res.status(500).json({ error: 'Error occurred during user registration.' });
  }
});

app.post('/login', async (req, res) => {
    const { username, user_password } = req.body || {};
    if (!username || !user_password) { return res.status(400).json({ error: 'Username and password are required.' }); }
    try {
        const [rows] = await pool.query('SELECT * FROM user_censei WHERE Username = ?', [username] );
        if (rows.length === 0) { return res.status(401).json({ error: 'Invalid username or password' }); }
        
        const user = rows[0];
        const passwordMatches = await bcrypt.compare(user_password, user.user_password);
        
        if (!passwordMatches) { return res.status(401).json({ error: 'Invalid username or password' }); }
        if (user.user_role?.toLowerCase() !== 'admin') { return res.status(403).json({ error: 'You do not have permission to log in here.' }); }
        
        res.status(200).json({ message: 'Login successful', user: { userid: user.userid, user_name: user.user_name, username: user.username, user_role: user.user_role }, });
    } catch (err) {
        console.error('Login Error:', err);
        res.status(500).json({ error: 'Database error during login' });
    }
});

app.get('/departments', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT departmentid, department_name FROM department ORDER BY department_name ASC;');
    res.status(200).json(rows);
  } catch (error) {
    console.error('Error fetching departments:', error);
    res.status(500).json({ error: 'Failed to fetch departments.' });
  }
});

app.post('/check-user', async (req, res) => {
  const { username, user_email } = req.body;
  if (!username && !user_email) {
    return res.status(400).json({ error: 'Username or email must be provided.' });
  }
  try {
    const conditions = [];
    const values = [];
    if (username) {
      conditions.push(`username = ?`);
      values.push(username);
    }
    if (user_email) {
      conditions.push(`user_email = ?`);
      values.push(user_email);
    }
    const query = `SELECT username, user_email FROM user_censei WHERE ${conditions.join(' OR ')}`;
    const [rows] = await pool.query(query, values);
    
    const response = {
      usernameExists: false,
      emailExists: false,
    };
    for (let row of rows) {
      if (row.username === username) response.usernameExists = true;
      if (row.user_email === user_email) response.emailExists = true;
    }
    res.status(200).json(response);
  } catch (error) {
    console.error('Error checking user existence:', error);
    res.status(500).json({ error: 'Failed to check username/email.' });
  }
});

app.get('/users/:id', async (req, res) => {
  try {
    const [rows] = await pool.query('SELECT * FROM user_censei WHERE userid = ?', [req.params.id]);
    if (rows.length === 0) {
        return res.status(404).json({ error: 'User not found' });
    }
    
    const { user_password, ...userWithoutPassword } = rows[0];
    res.json(userWithoutPassword);

  } catch (err) {
    console.error('User fetch error:', err);
    res.status(500).json({ error: 'Database error' });
  }
});

app.patch('/users/:id', async (req, res) => {
  const { id } = req.params;
  const { user_email, user_phonenumber } = req.body;

  if (!user_email) {
    return res.status(400).json({ error: 'Email field is required.' });
  }

  try {
    const [updateResult] = await pool.query(
      `UPDATE user_censei SET user_email = ?, user_phonenumber = ? WHERE userid = ?`,
      [user_email, user_phonenumber, id]
    );

    if (updateResult.affectedRows === 0) {
      return res.status(404).json({ error: 'User not found' });
    }

    const [updatedUserRows] = await pool.query('SELECT * FROM user_censei WHERE userid = ?', [id]);
    res.json(updatedUserRows[0]);

  } catch (err) {
    console.error('User update error:', err);
    if (err.code === 'ER_DUP_ENTRY') {
       return res.status(409).json({ error: 'This email is already in use.' });
    }
    res.status(500).json({ error: 'Database error during update.' });
  }
});

// =======================
//   File Upload Setup
// =======================

const uploadDir = path.join(__dirname, 'uploads');
if (!fs.existsSync(uploadDir)) fs.mkdirSync(uploadDir);

const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, uploadDir);
  },
  filename: (req, file, cb) => {
    const uniqueName = `${Date.now()}-${file.originalname.replace(/\s+/g, '_')}`;
    cb(null, uniqueName);
  }
});

const fileFilter = (req, file, cb) => {
  const allowedTypes = /jpeg|jpg|png|gif|pdf/;
  const extname = allowedTypes.test(path.extname(file.originalname).toLowerCase());
  const mimetype = allowedTypes.test(file.mimetype);

  if (mimetype && extname) {
    return cb(null, true);
  } else {
    cb(new Error('Error: Only images (jpeg, jpg, png, gif) and PDF files are allowed!'));
  }
};

const upload = multer({ 
  storage: storage,
  fileFilter: fileFilter,
  limits: { fileSize: 10 * 1024 * 1024 } // 10MB file size limit
});


// ===================================
//   RECYCLABLE WASTE DATA ENTRY API
// ===================================

app.post('/add-recycle', async (req, res) => {
  let { rwaste_estimated_weight, rwaste_actual_weight, collection_method, rwaste_type, collection_from, faculty_name, faculty_program_name, userid } = req.body;
  const estimatedWeight = parseFloat(rwaste_estimated_weight);
  const actualWeight = rwaste_actual_weight ? parseFloat(rwaste_actual_weight) : estimatedWeight;
  collection_method = collection_method?.trim() || 'Manual Form';
  if (collection_from === 'Faculty' && faculty_name?.trim()) {
    collection_from = faculty_name.trim();
  }
  if (collection_method === 'Faculty Programs' && faculty_program_name?.trim()) {
    collection_method = `Program: ${faculty_program_name.trim()}`;
  }
  if (!estimatedWeight || !actualWeight || !collection_method || !collection_from?.trim() || !rwaste_type?.trim() || !userid) {
    return res.status(400).json({ error: 'Missing required fields, including user ID.' });
  }

  let connection;
  try {
    connection = await pool.getConnection();
    await connection.beginTransaction();

    const now = new Date();
    const insertQuery = `INSERT INTO recyclable_waste (rwaste_estimated_weight, rwaste_actual_weight, collection_method, rwaste_datetaken, rwaste_type, collection_from) VALUES (?, ?, ?, ?, ?, ?);`;
    const [result] = await connection.query(insertQuery, [estimatedWeight, actualWeight, collection_method, now, rwaste_type, collection_from]);
    
    const rwasteid = result.insertId;
    
    const userEntryQuery = 'INSERT INTO user_entry_recyclable (rwasteid, userid) VALUES (?, ?)';
    await connection.query(userEntryQuery, [rwasteid, userid]);

    const [newWasteEntry] = await connection.query('SELECT * FROM recyclable_waste WHERE rwasteid = ?', [rwasteid]);

    await connection.commit();
    res.status(201).json({ message: 'Recyclable waste inserted successfully', waste: newWasteEntry[0] });
  } catch (err) {
    if (connection) await connection.rollback();
    console.error('Insert Waste Error:', err);
    res.status(500).json({ error: 'Error inserting recyclable waste.' });
  } finally {
    if (connection) connection.release();
  }
});

app.post('/upload-recycle-files', upload.array('rwaste_photos', 10), async (req, res) => {
  const { rwasteid } = req.body;
  if (!rwasteid || !req.files || req.files.length === 0) {
    if (req.files) { for (const file of req.files) { fs.unlinkSync(file.path); } }
    return res.status(400).json({ error: 'Missing rwasteid or no files uploaded.' });
  }

  let connection;
  try {
    connection = await pool.getConnection();
    await connection.beginTransaction();

    for (const file of req.files) {
      const insertFileQuery = `INSERT INTO file (file_name, file_type, file_size_kb, file_created, file_path, rwasteid) VALUES (?, ?, ?, ?, ?, ?)`;
      await connection.query(insertFileQuery, [file.originalname, file.mimetype, (file.size / 1024).toFixed(2), new Date(), file.filename, rwasteid]);
    }

    await connection.commit();
    res.status(201).json({ message: 'Files uploaded and recorded successfully.' });
  } catch (err) {
    if (connection) await connection.rollback();
    console.error('Insert File Error:', err);
    for (const file of req.files) { 
      if (fs.existsSync(file.path)) fs.unlinkSync(file.path); 
    }
    res.status(500).json({ error: 'Error inserting files. Transaction rolled back.' });
  } finally {
    if (connection) connection.release();
  }
});

app.delete('/delete-recycle/:rwasteid', async (req, res) => {
    const { rwasteid } = req.params;
    if (!rwasteid) { return res.status(400).json({ error: 'Missing rwasteid parameter' }); }
    
    let connection;
    try {
        connection = await pool.getConnection();
        await connection.beginTransaction();

        const [fileRes] = await connection.query('SELECT file_path FROM file WHERE rwasteid = ?', [rwasteid]);
        for (const row of fileRes) {
            if (row.file_path) {
                const fullPath = path.join(uploadDir, path.basename(row.file_path));
                if (fs.existsSync(fullPath)) { fs.unlinkSync(fullPath); }
            }
        }

        await connection.query('DELETE FROM file WHERE rwasteid = ?', [rwasteid]);
        const [deleteRes] = await connection.query('DELETE FROM recyclable_waste WHERE rwasteid = ?', [rwasteid]);

        if (deleteRes.affectedRows === 0) { 
            throw new Error('Recyclable waste entry not found.'); 
        }

        await connection.commit();
        res.status(200).json({ message: 'Recyclable waste entry and its files deleted successfully.' });
    } catch (err) {
        if (connection) await connection.rollback();
        console.error('Delete recycle error:', err);
        res.status(500).json({ error: 'Error deleting recyclable waste entry.' });
    } finally {
        if (connection) connection.release();
    }
});


// ===================================
//   DYNAMIC DISPLAY/EDIT API
// ===================================

app.get('/api/recyclable-locations/:wasteType', async (req, res) => {
  try {
    const { wasteType } = req.params;
    const query = `
      SELECT DISTINCT collection_from 
      FROM recyclable_waste 
      WHERE LOWER(rwaste_type) = ? 
      AND collection_from IS NOT NULL AND collection_from <> ''
      ORDER BY collection_from ASC;
    `;
    const [rows] = await pool.query(query, [wasteType.toLowerCase()]);
    const locations = rows.map(row => row.collection_from);
    res.json(locations);
  } catch (err) {
    console.error('Error fetching recyclable locations for type:', err.message);
    res.status(500).json({ error: 'Failed to fetch locations for this waste type.' });
  }
});

app.get('/api/recyclable-types', async (req, res) => {
  try {
    const query = `
      SELECT DISTINCT LOWER(rwaste_type) AS type 
      FROM recyclable_waste 
      ORDER BY type ASC;
    `;
    const [rows] = await pool.query(query);
    const types = rows.map(row => row.type);
    res.json(types);
  } catch (err) {
    console.error('Error fetching recyclable types:', err.message);
    res.status(500).json({ error: 'Failed to fetch waste types.' });
  }
});

app.get('/api/recyclable/:wasteType', async (req, res) => {
  try {
    const { wasteType } = req.params;
    const { location, sort, startDate, endDate } = req.query; 

    let baseQuery = `
      SELECT 
         r.RwasteID, r.Rwaste_Estimated_Weight, r.Rwaste_Actual_Weight,
         r.collection_method, r.Rwaste_DateTaken, r.collection_from AS location_name, r.Rwaste_type,
         f.file_path
      FROM recyclable_waste r
      JOIN file f ON r.rwasteid = f.rwasteid
      WHERE LOWER(r.rwaste_type) = ?
    `;
    const params = [wasteType.toLowerCase()];

    if (location && location !== 'all') {
        params.push(location);
        baseQuery += ` AND r.collection_from = ?`; 
    }

    if (startDate) {
        params.push(startDate);
        baseQuery += ` AND r.Rwaste_DateTaken >= ?`;
    }

    if (endDate) {
        params.push(endDate);
        baseQuery += ` AND r.Rwaste_DateTaken <= ?`;
    }

    if (sort === 'true') {
        baseQuery += ` ORDER BY r.Rwaste_Actual_Weight ASC, r.Rwaste_DateTaken DESC`;
    } else {
        baseQuery += ` ORDER BY r.Rwaste_DateTaken DESC`;
    }

    const [rows] = await pool.query(baseQuery, params);
    res.json(rows);
  } catch (err) {
    console.error(err.message);
    res.status(500).send('Server error');
  }
});

app.patch('/api/recyclable/:wasteType/:id', async (req, res) => {
    const { id } = req.params;
    const { rwaste_type, rwaste_actual_weight } = req.body;

    if (!rwaste_type || rwaste_actual_weight === undefined) {
        return res.status(400).json({ error: 'Both Waste Type and Actual Weight are required fields.' });
    }

    try {
        const [updateResult] = await pool.query(
            `UPDATE recyclable_waste
             SET rwaste_type = ?, rwaste_actual_weight = ?
             WHERE RwasteID = ?;`,
            [rwaste_type, rwaste_actual_weight, id]
        );

        if (updateResult.affectedRows > 0) {
            const [updatedWaste] = await pool.query('SELECT * FROM recyclable_waste WHERE RwasteID = ?', [id]);
            res.status(200).json({
                message: 'Waste data updated successfully.',
                updatedWaste: updatedWaste[0]
            });
        } else {
            res.status(404).json({ error: 'Waste data not found with the provided ID.' });
        }
    } catch (err) {
        console.error('Error updating waste:', err.message);
        res.status(500).json({ error: 'Error updating waste data.' });
    }
});

app.delete('/api/recyclable/:wasteType/:id', async (req, res) => {
    const { id } = req.params;
    let connection;
    let filePathToDelete = '';

    try {
        connection = await pool.getConnection();
        await connection.beginTransaction();

        const [fileResult] = await connection.query('SELECT file_path FROM file WHERE rwasteid = ?', [id]);
        if (fileResult.length > 0) {
            filePathToDelete = fileResult[0].file_path;
        }

        await connection.query('DELETE FROM file WHERE rwasteid = ?', [id]);
        const [wasteResult] = await connection.query('DELETE FROM recyclable_waste WHERE rwasteid = ?', [id]);

        if (wasteResult.affectedRows === 0) {
            await connection.rollback();
            return res.status(404).json({ error: 'Entry not found' });
        }

        await connection.commit();
        
        if (filePathToDelete) {
            const fullPath = path.join(__dirname, 'uploads', path.basename(filePathToDelete));
            fs.unlink(fullPath, (err) => {
                if (err) console.error('Error deleting physical file:', err);
            });
        }

        res.json({ message: 'Entry and associated file deleted successfully' });
    } catch (error) {
        if (connection) await connection.rollback();
        console.error('Error deleting waste entry:', error);
        res.status(500).json({ error: 'Internal server error' });
    } finally {
        if (connection) connection.release();
    }
});

// =================================
//   Dynamic Hazardous Waste API
// =================================

app.get('/api/hazardous-types', async (req, res) => {
  try {
    const query = `
      SELECT DISTINCT hwaste_code, hwaste_type 
      FROM hazardous_waste 
      WHERE hwaste_code IS NOT NULL AND hwaste_type IS NOT NULL
      ORDER BY hwaste_code ASC;
    `;
    const [rows] = await pool.query(query);
    res.json(rows);
  } catch (err) {
    console.error('Error fetching hazardous types:', err.message);
    res.status(500).json({ error: 'Failed to fetch hazardous waste types.' });
  }
});

app.get('/api/hazardous-locations/:wasteCode', async (req, res) => {
  try {
    const { wasteCode } = req.params;
    const query = `
      SELECT DISTINCT d.departmentid, d.department_name
      FROM hazardous_waste h
      JOIN department d ON h.departmentid = d.departmentid
      WHERE h.hwaste_code = ?
      AND h.departmentid IS NOT NULL
      ORDER BY d.department_name ASC;
    `;
    const [rows] = await pool.query(query, [wasteCode]);
    res.json(rows);
  } catch (err) {
    console.error('Error fetching hazardous departments for code:', err.message);
    res.status(500).json({ error: 'Failed to fetch departments for this waste code.' });
  }
});

app.get('/api/hazardous/:wasteCode', async (req, res) => {
  try {
    const { wasteCode } = req.params;
    const { location, sort, startDate, endDate } = req.query; 

    let baseQuery = `
      SELECT 
         h.hwasteid, h.hwaste_name, h.hwaste_code, h.hwaste_estimated_weight, 
         h.hwaste_actual_weight, h.storage_location, h.hwaste_datetaken, h.hwaste_type,
         hf.hfile_path as file_path,
         d.department_name
      FROM hazardous_waste h
      JOIN hfile hf ON h.hwasteid = hf.hwasteid
      JOIN department d ON h.departmentid = d.departmentid
      WHERE h.hwaste_code = ?
    `;
    const params = [wasteCode];

    if (location && location !== 'all') {
      params.push(location);
      baseQuery += ` AND h.departmentid = ?`;
    }
    if (startDate) {
      params.push(startDate);
      baseQuery += ` AND h.hwaste_datetaken >= ?`;
    }
    if (endDate) {
      params.push(endDate);
      baseQuery += ` AND h.hwaste_datetaken <= ?`;
    }

    if (sort === 'true') {
      baseQuery += ` ORDER BY h.hwaste_actual_weight ASC, h.hwaste_datetaken DESC`;
    } else {
      baseQuery += ` ORDER BY h.hwaste_datetaken DESC`;
    }

    const [rows] = await pool.query(baseQuery, params);
    res.json(rows);
  } catch (err) {
    console.error(err.message);
    res.status(500).send('Server error');
  }
});

app.patch('/api/hazardous/:wasteCode/:id', async (req, res) => {
    const { id } = req.params;
    const { hwaste_code, hwaste_type, hwaste_actual_weight } = req.body;

    if (!hwaste_code || !hwaste_type || hwaste_actual_weight === undefined) {
        return res.status(400).json({ error: 'Waste Code, Type, and Actual Weight are required.' });
    }

    try {
        const [updateResult] = await pool.query(
            `UPDATE hazardous_waste
             SET hwaste_code = ?, hwaste_type = ?, hwaste_actual_weight = ?
             WHERE hwasteid = ?;`,
            [hwaste_code, hwaste_type, hwaste_actual_weight, id]
        );

        if (updateResult.affectedRows > 0) {
            const [updatedWaste] = await pool.query('SELECT * FROM hazardous_waste WHERE hwasteid = ?', [id]);
            res.status(200).json({
                message: 'Hazardous waste data updated successfully.',
                updatedWaste: updatedWaste[0]
            });
        } else {
            res.status(404).json({ error: 'Hazardous waste data not found.' });
        }
    } catch (err) {
        console.error('Error updating hazardous waste:', err.message);
        res.status(500).json({ error: 'Error updating hazardous waste data.' });
    }
});

app.delete('/api/hazardous/:wasteCode/:id', async (req, res) => {
    const { id } = req.params;
    let connection;
    let filePathToDelete = '';

    try {
        connection = await pool.getConnection();
        await connection.beginTransaction();
        
        const [fileResult] = await connection.query('SELECT hfile_path FROM hfile WHERE hwasteid = ?', [id]);
        if (fileResult.length > 0 && fileResult[0].hfile_path) {
            filePathToDelete = fileResult[0].hfile_path;
        }

        await connection.query('DELETE FROM hfile WHERE hwasteid = ?', [id]);
        const [wasteResult] = await connection.query('DELETE FROM hazardous_waste WHERE hwasteid = ?', [id]);
        
        if (wasteResult.affectedRows === 0) {
            await connection.rollback();
            return res.status(404).json({ error: 'Entry not found' });
        }

        await connection.commit();
        
        if (filePathToDelete) {
            const fullPath = path.join(__dirname, 'uploads', path.basename(filePathToDelete));
             fs.unlink(fullPath, (err) => {
                if (err) console.error('Error deleting physical file:', err);
            });
        }
        
        res.json({ message: 'Hazardous waste entry and associated file deleted successfully' });

    } catch (error) {
        if (connection) await connection.rollback();
        console.error('Error deleting hazardous waste entry:', error);
        res.status(500).json({ error: 'Internal server error' });
    } finally {
        if (connection) connection.release();
    }
});


// =======================
//   Dashboard Endpoints
// =======================

app.get('/api/monthly-recyclable', async (req, res) => {
  const year = req.query.year;
  if (!year) { 
    return res.status(400).json({ error: 'Year query parameter is required.' }); 
  }
  try {
    const [rows] = await pool.query(`
      SELECT
        DATE_FORMAT(Rwaste_DateTaken, '%b') AS month,
        SUM(
            CASE
                WHEN LOWER(rwaste_type) IN ('waste cooking oil', 'paper')
                THEN Rwaste_Actual_Weight
                ELSE 0
            END
        ) AS organic_weight,
        SUM(
            CASE
                WHEN LOWER(rwaste_type) NOT IN ('waste cooking oil', 'paper')
                THEN Rwaste_Actual_Weight
                ELSE 0
            END
        ) AS inorganic_weight
      FROM RECYCLABLE_WASTE
      WHERE YEAR(Rwaste_DateTaken) = ?
      GROUP BY DATE_FORMAT(Rwaste_DateTaken, '%b'), MONTH(Rwaste_DateTaken)
      ORDER BY MONTH(Rwaste_DateTaken);
    `, [year]);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/pie-recyclable', async (req, res) => {
  const year = req.query.year;
  if (!year) { 
    return res.status(400).json({ error: 'Year query parameter is required.' }); 
  }
  try {
    const [rows] = await pool.query(`
      SELECT LOWER(rwaste_type) AS type, SUM(Rwaste_Actual_Weight) AS total
      FROM RECYCLABLE_WASTE
      WHERE YEAR(Rwaste_DateTaken) = ?
      GROUP BY LOWER(rwaste_type);
    `, [year]);
    res.json(rows);
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/monthly-hazardous', async (req, res) => {
  const year = req.query.year;
  if (!year) { return res.status(400).json({ error: 'Year query parameter is required.'}); }
  try {
    const [rows] = await pool.query(`
      SELECT 
        code,
        type,
        total_weight
      FROM v_hazardous_summary_by_type
      WHERE year = ?
      ORDER BY code, type;
    `, [year]);
    res.json(rows);
  } catch (err) {
    console.error("Error fetching monthly hazardous summary:", err);
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/pie-hazardous', async (req, res) => {
  const year = req.query.year;
  if (!year) { return res.status(400).json({ error: 'Year query parameter is required.'}); }
  try {
    const [rows] = await pool.query(`
      SELECT code, type, total
      FROM v_hazardous_summary
      WHERE year = ?
      ORDER BY code;
    `, [year]);
    res.json(rows);
  } catch (err) {
    console.error("Error fetching hazardous pie data:", err);
    res.status(500).json({ error: err.message });
  }
});

app.get('/api/available-years', async (req, res) => {
  try {
    const [rows] = await pool.query(`
      SELECT DISTINCT YEAR(rwaste_datetaken) AS year
      FROM recyclable_waste
      UNION
      SELECT DISTINCT YEAR(hwaste_datetaken) AS year
      FROM hazardous_waste
      ORDER BY year DESC;
    `);
    const years = rows.map(r => parseInt(r.year)).filter(y => !isNaN(y));
    res.json(years);
  } catch (err) {
    console.error('Error fetching available years:', err);
    res.status(500).json({ error: 'Failed to fetch available years.' });
  }
});

// =======================
//   HAZARDOUS DATA ENTRY
// =======================

app.post('/add-hazardous-waste', upload.array('hwaste_photos', 10), async (req, res) => {
  const {
    hwaste_name,
    hwaste_code,
    hwaste_estimated_weight,
    storage_location,
    departmentid,
    hwaste_datetaken,
    hwaste_type,
    userid
  } = req.body;
  
  const estimatedWeight = parseFloat(hwaste_estimated_weight);
  const actualWeight = req.body.hwaste_actual_weight ? parseFloat(req.body.hwaste_actual_weight) : estimatedWeight;
  const photoFiles = req.files;

  if (!hwaste_name || !hwaste_code || !hwaste_estimated_weight || !storage_location || !departmentid || !hwaste_datetaken || !hwaste_type || !userid || !photoFiles || photoFiles.length === 0) {
    if (photoFiles) {
        for(const file of photoFiles) {
            fs.unlink(path.join(uploadDir, file.filename), (unlinkErr) => {
                if(unlinkErr) console.error("Error deleting orphaned file on validation fail:", unlinkErr);
            });
        }
    }
    return res.status(400).json({ error: 'All fields (except actual weight), user ID, and at least one file are required.' });
  }
  
  if (isNaN(estimatedWeight) || isNaN(actualWeight)) {
    for(const file of photoFiles) { fs.unlinkSync(file.path); }
    return res.status(400).json({ error: 'Estimated and actual weights must be valid numbers.' });
  }

  let connection;
  try {
    connection = await pool.getConnection();
    await connection.beginTransaction();
    
    const wasteQuery = `
      INSERT INTO hazardous_waste
        (hwaste_name, hwaste_estimated_weight, hwaste_actual_weight, storage_location, hwaste_datetaken, hwaste_code, hwaste_type, departmentid)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `;
    const wasteValues = [hwaste_name, estimatedWeight, actualWeight, storage_location, hwaste_datetaken, hwaste_code, hwaste_type, departmentid];
    const [newWasteEntryResult] = await connection.query(wasteQuery, wasteValues);
    const newWasteId = newWasteEntryResult.insertId;

    const userEntryQuery = 'INSERT INTO user_entry_hazardous (hwasteid, userid) VALUES (?, ?)';
    await connection.query(userEntryQuery, [newWasteId, userid]);

    for (const photoFile of photoFiles) {
        const fileQuery = `
            INSERT INTO hfile (hwasteid, hfile_name, hfile_type, hfile_size_kb, hfile_created, hfile_path) 
            VALUES (?, ?, ?, ?, NOW(), ?)
        `;
        const fileValues = [ newWasteId, photoFile.originalname, photoFile.mimetype, (photoFile.size / 1024).toFixed(2), photoFile.filename ];
        await connection.query(fileQuery, fileValues);
    }
    
    await connection.commit();
    
    const [finalData] = await pool.query('SELECT * FROM hazardous_waste WHERE hwasteid = ?', [newWasteId]);
    res.status(201).json({ message: 'Hazardous waste entry and files added successfully!', data: finalData[0] });
  
  } catch (err) {
    if (connection) await connection.rollback();
    console.error('Insert Error:', err);
    if (photoFiles) {
        for(const file of photoFiles) {
            fs.unlink(path.join(uploadDir, file.filename), (unlinkErr) => {
                if(unlinkErr) console.error("Error deleting orphaned file on DB fail:", unlinkErr);
            });
        }
    }
    res.status(500).json({ error: 'Database insert failed' });
  } finally {
    if (connection) connection.release();
  }
});

// =======================
//   Server Start
// =======================

app.listen(port, () => {
    console.log(`Server running on http://localhost:${port}`);
});