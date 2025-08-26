import React, { useState, useEffect } from 'react';
import axios from 'axios';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

function EntryHazardous() {
  const [formData, setFormData] = useState({
    hwaste_name: '',
    hwaste_code: '',
    hwaste_type: '',
    hwaste_estimated_weight: '',
    hwaste_actual_weight: '',
    storage_location: '',
    hwaste_datetaken: new Date().toISOString().split('T')[0],
    departmentid: ''
  });

  const [files, setFiles] = useState(null);
  const [wasteCategories, setWasteCategories] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [isOtherWaste, setIsOtherWaste] = useState(false);


  useEffect(() => {
    const storedUserID = sessionStorage.getItem('userID');
    if (!storedUserID) {
      window.location.href = '/';
    }
    
    const fetchInitialData = async () => {
      try {
        const [catsRes, depsRes] = await Promise.all([
          axios.get('http://localhost:3000/api/hazardous-types'),
          axios.get('http://localhost:3000/departments')
        ]);
        setWasteCategories(catsRes.data);
        setDepartments(depsRes.data);
      } catch (error) {
        console.error("Failed to fetch initial data", error);
        window.alert('❌ Failed to load form data. Please refresh the page.');
      }
    };

    fetchInitialData();
  }, []);

  const handleChange = (e) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleFileChange = (e) => {
    setFiles(e.target.files);
  };
  
  const handleWasteTypeChange = (e) => {
    const selectedCode = e.target.value;

    if (selectedCode === 'other') {
      setIsOtherWaste(true);
      setFormData(prev => ({
        ...prev,
        hwaste_code: '',
        hwaste_type: '',
      }));
    } else {
      setIsOtherWaste(false);
      const selectedWaste = wasteCategories.find(opt => opt.hwaste_code === selectedCode);
      if (selectedWaste) {
        setFormData(prev => ({
          ...prev,
          hwaste_code: selectedWaste.hwaste_code,
          hwaste_type: selectedWaste.hwaste_type,
        }));
      }
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    const userid = sessionStorage.getItem('userID');
    if (!userid) {
      window.alert('❌ User not authenticated. Please log in again.');
      return;
    }

    if (!files || files.length === 0) {
        window.alert('❌ Please upload at least one photo or file.');
        return;
    }

    // --- NEW VALIDATION LOGIC FOR "OTHER" ---
    // If 'isOtherWaste' is true, check that the custom code and type fields are not empty.
    if (isOtherWaste && (!formData.hwaste_code.trim() || !formData.hwaste_type.trim())) {
      window.alert('❌ When "Other" is selected, you must provide a New Waste Code and New Waste Type.');
      return; // Stop the submission here
    }
    // --- END OF NEW VALIDATION LOGIC ---

    try {
      // Step 1: Send text data to create the record and get the ID back.
      const dataWithUser = { ...formData, userid };
      const wasteResponse = await axios.post('http://localhost:3000/add-hazardous', dataWithUser);
      
      const { hwasteid } = wasteResponse.data.waste;

      // Step 2: Create new FormData for files and upload them with the new ID.
      const fileFormData = new FormData();
      fileFormData.append('hwasteid', hwasteid);
      for (let i = 0; i < files.length; i++) {
        fileFormData.append('hwaste_photos', files[i]);
      }

      await axios.post('http://20.205.132.62/upload-hazardous-files', fileFormData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });

      window.alert(`✅ Record ${hwasteid} and files submitted successfully!`);
      
      // Reset the form state completely
      setFormData({
        hwaste_name: '',
        hwaste_code: '',
        hwaste_type: '',
        hwaste_estimated_weight: '',
        hwaste_actual_weight: '',
        storage_location: '',
        hwaste_datetaken: new Date().toISOString().split('T')[0],
        departmentid: ''
      });
      setFiles(null);
      setIsOtherWaste(false);
      document.getElementById('file-input-hazardous').value = ''; // Clear the file input visually

    } catch (err) {
      console.error('Error during submission:', err);
      const errorMessage = err.response?.data?.error || 'Submission failed. Please try again.';
      window.alert(`❌ ${errorMessage}`);
    }
  };

  // Styles object
  const styles = {
    body: {
      backgroundImage: "url('/Background image Website Data Sisa.png')",
      backgroundSize: 'cover',
      backgroundRepeat: 'no-repeat',
      backgroundPosition: 'center',
      backgroundAttachment: 'fixed',
      minHeight: '100vh',
      paddingTop: '100px',
    },
    container: {
      maxWidth: '600px',
      margin: 'auto',
      padding: '20px',
      background: '#fccccc',
      borderRadius: '10px',
      boxShadow: '0 4px 10px rgba(0,0,0,0.1)'
    },
    inputGroup: { marginBottom: '15px' },
    label: { display: 'block', fontWeight: 'bold', marginBottom: '5px' },
    input: {
      width: '100%',
      padding: '10px',
      borderRadius: '6px',
      border: '1px solid #ccc'
    },
    button: {
      marginTop: '15px',
      padding: '10px 20px',
      backgroundColor: '#dc3545',
      color: '#fff',
      border: 'none',
      borderRadius: '5px',
      cursor: 'pointer'
    }
  };

  return (
    <div style={styles.body}>
      <nav className="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div className="container-fluid">
          <a className="navbar-brand" href="/Dashboard">CENSEI</a>
          <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span className="navbar-toggler-icon"></span>
          </button>
          <div className="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul className="navbar-nav">
              <li className="nav-item"><a className="nav-link" href="/Organic">Recyclable Organic & Inorganic</a></li>
              <li className="nav-item"><a className="nav-link" href="/Hazardous">Hazardous</a></li>
              <li className="nav-item dropdown">
                <button
                  className="nav-link dropdown-toggle btn btn-link text-white"
                  id="dataEntryDropdown"
                  data-bs-toggle="dropdown"
                  aria-expanded="false"
                  style={{ textDecoration: 'none' }}
                >
                  Data Entry
                </button>
                <ul className="dropdown-menu">
                  <li><a className="dropdown-item" href="/entry_organic">Recyclable Organic & Inorganic</a></li>
                  <li><a className="dropdown-item" href="/entry_hazardous">Hazardous</a></li>
                </ul>
              </li>
              <li className="nav-item"><a className="nav-link" href="https://censei.utem.edu.my/index.php/ms/" target="_blank" rel="noopener noreferrer">About Us</a></li>
              <li className="nav-item d-flex align-items-center ms-3">
                <i className="bi bi-person-circle" style={{ fontSize: '1.8rem', color: 'white', cursor: 'pointer' }} data-bs-toggle="modal" data-bs-target="#profileModal" />
              </li>
            </ul>
          </div>
        </div>
      </nav>

      <div className="container mt-5">
        <div className="text-end mb-3">
          <a href="/Dashboard" className="btn btn-secondary">← Back to Dashboard</a>
        </div>

        <div style={styles.container}>
          <h2 className="text-center mb-4">Enter Hazardous Waste</h2>
          <form onSubmit={handleSubmit}>
            <div style={styles.inputGroup}>
              <label style={styles.label}>Waste Category</label>
              <select value={isOtherWaste ? 'other' : formData.hwaste_code} onChange={handleWasteTypeChange} required style={styles.input}>
                <option value="" disabled>Select waste category</option>
                {wasteCategories.map(opt => (
                    <option key={opt.hwaste_code} value={opt.hwaste_code}>{`${opt.hwaste_type} (${opt.hwaste_code})`}</option>
                ))}
                <option value="other">Other...</option>
              </select>
            </div>
            
            {isOtherWaste && (
              <>
                <div style={styles.inputGroup}>
                  <label style={styles.label}>New Waste Code</label>
                  <input type="text" name="hwaste_code" value={formData.hwaste_code} onChange={handleChange} required style={styles.input} placeholder="e.g., SW501" />
                </div>
                <div style={styles.inputGroup}>
                  <label style={styles.label}>New Waste Type</label>
                  <input type="text" name="hwaste_type" value={formData.hwaste_type} onChange={handleChange} required style={styles.input} placeholder="e.g., Product" />
                </div>
              </>
            )}

            <div style={styles.inputGroup}>
              <label style={styles.label}>General Name</label>
              <input type="text" name="hwaste_name" value={formData.hwaste_name} onChange={handleChange} required style={styles.input} />
            </div>
            <div style={styles.inputGroup}>
              <label style={styles.label}>Estimated Weight (kg)</label>
              <input type="number" step="0.01" min="0" name="hwaste_estimated_weight" value={formData.hwaste_estimated_weight} onChange={handleChange} required style={styles.input} />
            </div>
            <div style={styles.inputGroup}>
              <label style={styles.label}>Actual Weight (kg)</label>
              <input 
                type="number" 
                step="0.01" 
                min="0"
                name="hwaste_actual_weight" 
                value={formData.hwaste_actual_weight} 
                onChange={handleChange} 
                style={styles.input}
                placeholder="Same as estimated if left blank" 
              />
            </div>
            <div style={styles.inputGroup}>
              <label style={styles.label}>Storage Location</label>
              <input type="text" name="storage_location" value={formData.storage_location} onChange={handleChange} required style={styles.input} />
            </div>
            
            <div style={styles.inputGroup}>
              <label style={styles.label}>Department</label>
              <select name="departmentid" value={formData.departmentid} onChange={handleChange} required style={styles.input}>
                  <option value="" disabled>Select department</option>
                  {departments.map(dept => (
                      <option key={dept.departmentid} value={dept.departmentid}>{dept.department_name}</option>
                  ))}
              </select>
            </div>

            <div style={styles.inputGroup}>
              <label style={styles.label}>Upload Photos/Files (can select multiple)</label>
              <input
                id="file-input-hazardous"
                type="file"
                name="hwaste_photos"
                accept="image/*,application/pdf"
                onChange={handleFileChange}
                required
                multiple 
                style={styles.input}
              />
            </div>
            <button type="submit" style={styles.button}>Submit</button>
          </form>
        </div>
      </div>

      <div className="modal fade" id="profileModal" tabIndex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div className="modal-dialog modal-lg modal-dialog-centered">
          <div className="modal-content bg-dark text-white">
            <div className="modal-header">
              <h5 className="modal-title">User Profile</h5>
              <button type="button" className="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div className="modal-body p-0" style={{ height: '500px' }}>
              <iframe src="/profile" width="100%" height="100%" style={{ border: 'none' }} title="User Profile" />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

export default EntryHazardous;