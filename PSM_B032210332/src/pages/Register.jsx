import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

// A simple modal component for displaying success or error popups
function PopupModal({ message, onClose, isError }) {
  const styles = {
    modalOverlay: {
      position: 'fixed',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      backgroundColor: 'rgba(0, 0, 0, 0.5)',
      display: 'flex',
      justifyContent: 'center',
      alignItems: 'center',
      zIndex: 1000,
    },
    modalContent: {
      backgroundColor: 'white',
      padding: '20px 40px',
      borderRadius: '8px',
      textAlign: 'center',
      boxShadow: '0 4px 10px rgba(0, 0, 0, 0.2)',
    },
    modalMessage: {
      marginBottom: '20px',
      color: isError ? 'red' : 'green',
      fontSize: '1.1rem',
    },
    modalButton: {
      padding: '10px 20px',
      border: 'none',
      borderRadius: '6px',
      cursor: 'pointer',
      backgroundColor: '#4CAF50',
      color: 'white',
    },
  };

  return (
    <div style={styles.modalOverlay}>
      <div style={styles.modalContent}>
        <p style={styles.modalMessage}>{message}</p>
        <button onClick={onClose} style={styles.modalButton}>
          Close
        </button>
      </div>
    </div>
  );
}

function Register() {
  const navigate = useNavigate();

  const [departments, setDepartments] = useState([]);
  const [formData, setFormData] = useState({
    user_name: '',
    user_email: '',
    user_phonenumber: '',
    username: '',
    password: '',
    repassword: '',
    user_role: 'Staff',
    user_position: '',
    user_department: '',
    specialist: '',
  });

  const [errors, setErrors] = useState({});
  const [popup, setPopup] = useState({
    isOpen: false,
    message: '',
    isError: false,
  });

  useEffect(() => {
    const fetchDepartments = async () => {
      try {
        const response = await axios.get('http://localhost:3000/departments');
        setDepartments(response.data);
      } catch (error) {
        console.error('Failed to fetch departments:', error);
        setPopup({
          isOpen: true,
          message: '❌ Could not load department list.',
          isError: true,
        });
      }
    };
    fetchDepartments();
  }, []);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: null }));
    }
  };

  const validateForm = () => {
    const newErrors = {};
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // Checks for basic email structure

    // --- NEW: EMAIL FORMAT VALIDATION ---
    if (!emailRegex.test(formData.user_email)) {
        newErrors.user_email = 'Please enter a valid email format (e.g., user@example.com).';
    }

    if (formData.password.length < 8) {
      newErrors.password = 'Password must be at least 8 characters long.';
    }
    if (formData.password !== formData.repassword) {
      newErrors.repassword = 'Passwords do not match.';
    }
    if (!formData.user_department) {
      newErrors.user_department = 'Please select a department.';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validateForm()) {
      return;
    }

    try {
      const { repassword, ...postData } = formData;
      const response = await axios.post('http://localhost:3000/add-user', postData);
      setPopup({
        isOpen: true,
        message: `✅ ${response.data.message}`,
        isError: false,
      });
      setTimeout(() => {
        setPopup({ isOpen: false, message: '', isError: false });
        navigate('/');
      }, 2000);
    } catch (err) {
      console.error('Registration error:', err);
      const errorMsg = err.response?.data?.error || '';
      
      // --- UPDATED ERROR HANDLING ---
      if (errorMsg === 'Username already exists.') {
        setErrors(prev => ({ ...prev, username: errorMsg }));
      } else if (errorMsg === 'Email already exists.') {
        setErrors(prev => ({ ...prev, user_email: errorMsg }));
      } else if (errorMsg === 'Invalid email format.') { // Catch new backend error
        setErrors(prev => ({ ...prev, user_email: errorMsg }));
      } else if (errorMsg === 'Phone number is already registered.') {
        setErrors(prev => ({ ...prev, user_phonenumber: errorMsg }));
      } else if (errorMsg.includes('Invalid department')) {
        setErrors(prev => ({ ...prev, user_department: errorMsg }));
      } else {
        const displayMsg = errorMsg || 'Registration failed.';
        setPopup({
          isOpen: true,
          message: `❌ ${displayMsg}`,
          isError: true,
        });
      }
    }
  };

  const styles = {
    body: {
      fontFamily: 'Arial, sans-serif',
      backgroundImage: 'url("/Background image Website Data Sisa.png")',
      backgroundSize: 'cover',
      backgroundRepeat: 'no-repeat',
      backgroundPosition: 'center',
      minHeight: '100vh',
      overflowY: 'auto',
      padding: '60px 20px',
      display: 'flex',
      justifyContent: 'center',
    },
    box: {
      backgroundColor: '#56dddb',
      padding: '30px 25px',
      borderRadius: '10px',
      maxWidth: '480px',
      width: '100%',
      boxShadow: '0 8px 20px rgba(0, 0, 0, 0.1)',
    },
    heading: {
      textAlign: 'center',
      marginBottom: '25px',
      color: '#333',
    },
    formGroup: {
      display: 'flex',
      flexDirection: 'column',
      marginBottom: '16px',
    },
    label: {
      marginBottom: '5px',
      fontWeight: '600',
      color: '#333',
      textAlign: 'left',
    },
    input: {
      padding: '10px 12px',
      borderRadius: '6px',
      border: '1px solid #ccc',
      backgroundColor: '#fdfdfd',
      fontSize: '1rem',
      width: '100%',
    },
    button: {
      padding: '10px',
      width: '100%',
      backgroundColor: '#4CAF50',
      color: 'white',
      border: 'none',
      borderRadius: '6px',
      cursor: 'pointer',
      fontWeight: 'bold',
      fontSize: '1rem',
    },
    errorText: {
      color: 'red',
      fontSize: '0.8rem',
      marginTop: '4px',
      textAlign: 'left',
    }
  };

  return (
    <div style={styles.body}>
      {popup.isOpen && (
        <PopupModal
          message={popup.message}
          isError={popup.isError}
          onClose={() => setPopup({ isOpen: false, message: '', isError: false })}
        />
      )}
      <div style={styles.box}>
        <h2 style={styles.heading}>REGISTRATION FOR MOBILE APP USER</h2>
        <form onSubmit={handleSubmit} noValidate>
          <div style={styles.formGroup}>
            <label style={styles.label}>Full Name</label>
            <input type="text" name="user_name" value={formData.user_name} onChange={handleChange} required style={styles.input} />
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Email</label>
            <input type="email" name="user_email" value={formData.user_email} onChange={handleChange} required style={styles.input} />
            {errors.user_email && <div style={styles.errorText}>{errors.user_email}</div>}
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Phone Number</label>
            <input type="tel" name="user_phonenumber" value={formData.user_phonenumber} onChange={handleChange} required style={styles.input} />
            {errors.user_phonenumber && <div style={styles.errorText}>{errors.user_phonenumber}</div>}
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Username</label>
            <input type="text" name="username" value={formData.username} onChange={handleChange} required style={styles.input} />
            {errors.username && <div style={styles.errorText}>{errors.username}</div>}
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Password</label>
            <input type="password" name="password" value={formData.password} onChange={handleChange} required style={styles.input} />
            {errors.password && <div style={styles.errorText}>{errors.password}</div>}
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Re-enter Password</label>
            <input type="password" name="repassword" value={formData.repassword} onChange={handleChange} required style={styles.input} />
            {errors.repassword && <div style={styles.errorText}>{errors.repassword}</div>}
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Position in UTeM</label>
            <input type="text" name="user_position" value={formData.user_position} onChange={handleChange} required style={styles.input} />
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>PTJ or Office</label>
            <select name="user_department" value={formData.user_department} onChange={handleChange} required style={styles.input}>
              <option value="">-- Select a PTJ/Office --</option>
              {departments.map((dept, index) => (
                <option key={index} value={dept.department_name}>
                  {dept.department_name}
                </option>
              ))}
            </select>
            {errors.user_department && <div style={styles.errorText}>{errors.user_department}</div>}
          </div>
          <div style={styles.formGroup}>
            <label style={styles.label}>Waste Management Group</label>
            <select name="specialist" value={formData.specialist} onChange={handleChange} style={styles.input}>
              <option value="">None (Optional)</option>
              <option value="recycle">Recycle</option>
              <option value="hazardous">Hazardous</option>
              <option value="Both">Both</option>
            </select>
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: '10px', marginTop: '20px' }}>
            <button type="button" onClick={() => navigate('/')} style={{ ...styles.button, backgroundColor: '#888' }}>
              Back
            </button>
            <button type="submit" style={styles.button}>
              Register
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default Register;