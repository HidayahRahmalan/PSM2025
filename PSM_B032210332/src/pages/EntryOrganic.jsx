import React, { useState, useEffect } from 'react';
import axios from 'axios';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

function EntryOrganic() {
  const [formData, setFormData] = useState({
    rwaste_estimated_weight: '',
    rwaste_actual_weight: '',
    collection_method: '',
    rwaste_type: '',
    collection_from: '',
    faculty_name: '',
    faculty_program_name: '',
  });

  const [files, setFiles] = useState(null);
  // The 'message' and 'messageType' states are no longer needed
  // const [message, setMessage] = useState('');
  // const [messageType, setMessageType] = useState('danger');

  const wasteTypes = [
    { value: 'Paper', label: 'Paper' },
    { value: 'Plastic', label: 'Plastic' },
    { value: 'Metal', label: 'Metal' },
    { value: 'Fabric', label: 'Fabric' },
    { value: 'Others (Glasses)', label: 'Others (Glasses)' },
    { value: 'Used Cooking Oil', label: 'Used Cooking Oil' }
  ];

  useEffect(() => {
    const storedUserID = sessionStorage.getItem('userID');
    if (!storedUserID) {
      window.location.href = '/';
    }
  }, []);

  const handleChange = (e) => {
    setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleFileChange = (e) => {
    setFiles(e.target.files);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    const userid = sessionStorage.getItem('userID');
    if (!userid) {
      window.alert('❌ User not authenticated. Please log in again.');
      return;
    }

    if (!files || files.length === 0) {
      // Replaced setMessage with alert()
      window.alert('❌ Please upload at least one photo.');
      return;
    }

    try {
      const dataWithUser = { ...formData, userid };
      const wasteResponse = await axios.post('http://localhost:3000/add-recycle', dataWithUser);
      const { rwasteid } = wasteResponse.data.waste;
      
      const fileFormData = new FormData();
      fileFormData.append('rwasteid', rwasteid);
      for (let i = 0; i < files.length; i++) {
        fileFormData.append('rwaste_photos', files[i]);
      }

      await axios.post('http://20.205.132.62:3000/upload-recycle-files', fileFormData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      // Replaced setMessage with alert()
      window.alert(`✅ Record ${rwasteid} and photos submitted successfully!`);
      
      setFormData({
        rwaste_estimated_weight: '', rwaste_actual_weight: '',
        collection_method: '', rwaste_type: '', collection_from: '',
        faculty_name: '', faculty_program_name: '',
      });
      setFiles(null);
      document.getElementById('file-input').value = '';

    } catch (err) {
      console.error('Error during submission:', err);
      const errorMessage = err.response?.data?.error || 'Submission failed. Please try again.';
      // Replaced setMessage with alert()
      window.alert(`❌ ${errorMessage}`);
    }
  };

  // Styles object is now cleaner without the message styles
  const styles = {
    body: { backgroundImage: "url('/Background image Website Data Sisa.png')", backgroundSize: 'cover', backgroundRepeat: 'no-repeat', backgroundPosition: 'center', backgroundAttachment: 'fixed', minHeight: '100vh', paddingTop: '100px' },
    container: { maxWidth: '600px', margin: 'auto', padding: '20px', background: '#c8facc', borderRadius: '10px', boxShadow: '0 4px 10px rgba(0,0,0,0.1)' },
    inputGroup: { marginBottom: '15px' },
    label: { display: 'block', fontWeight: 'bold', marginBottom: '5px' },
    input: { width: '100%', padding: '10px', borderRadius: '6px', border: '1px solid #ccc' },
    button: { marginTop: '15px', padding: '10px 20px', backgroundColor: '#28a745', color: '#fff', border: 'none', borderRadius: '5px', cursor: 'pointer' },
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
          <h2 className="text-center mb-4">Enter Recyclable Organic & Inorganic Waste</h2>
          
          <form onSubmit={handleSubmit}>
            <div style={styles.inputGroup}>
              <label style={styles.label}>Waste Type</label>
              <select name="rwaste_type" value={formData.rwaste_type} onChange={handleChange} required style={styles.input}>
                <option value="" disabled>Select waste type</option>
                {wasteTypes.map(type => (
                  <option key={type.value} value={type.value}>{type.label}</option>
                ))}
              </select>
            </div>
            
            <div style={styles.inputGroup}>
              <label style={styles.label}>Estimated Weight (kg)</label>
              <input type="number" step="0.01" min="0" name="rwaste_estimated_weight" value={formData.rwaste_estimated_weight} onChange={handleChange} required style={styles.input} />
            </div>

            <div style={styles.inputGroup}>
              <label style={styles.label}>Actual Weight (kg)</label>
              <input type="number" step="0.01" min="0" name="rwaste_actual_weight" value={formData.rwaste_actual_weight} onChange={handleChange} placeholder="Same as estimated if left blank" style={styles.input} />
            </div>
            
            <div style={styles.inputGroup}>
              <label style={styles.label}>Collection Method</label>
              <select name="collection_method" value={formData.collection_method} onChange={handleChange} required style={styles.input}>
                <option value="" disabled>Select method</option>
                <option value="UTeM Facilities">UTeM Facilities</option>
                <option value="Faculty Programs">Faculty Programs</option>
                <option value="Community Program">Community Program</option>
                <option value="No Program">No Program</option>
              </select>
            </div>

            {formData.collection_method === 'Faculty Programs' && (
              <div style={styles.inputGroup}>
                <label style={styles.label}>Program Name</label>
                <input type="text" name="faculty_program_name" value={formData.faculty_program_name} onChange={handleChange} required={formData.collection_method === 'Faculty Programs'} style={styles.input} />
              </div>
            )}
            
            <div style={styles.inputGroup}>
              <label style={styles.label}>Collection From</label>
              <select name="collection_from" value={formData.collection_from} onChange={handleChange} required style={styles.input}>
                <option value="" disabled>Select source</option>
                <option value="Individual">Individual</option>
                <option value="Faculty">Faculty</option>
                <option value="Other">Other</option>
              </select>
            </div>

            {formData.collection_from === 'Faculty' && (
              <div style={styles.inputGroup}>
                <label style={styles.label}>Faculty Name</label>
                <input 
                  type="text" 
                  name="faculty_name" 
                  value={formData.faculty_name} 
                  onChange={handleChange} 
                  required={formData.collection_from === 'Faculty'} 
                  style={styles.input} 
                  placeholder="e.g., FTMK"
                />
              </div>
            )}
            
            <div style={styles.inputGroup}>
              <label style={styles.label}>Upload Photo in jpg,png and pdf (can select multiple)</label>
              <input
                id="file-input"
                type="file"
                name="rwaste_photos"
                accept="image/*"
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

export default EntryOrganic;