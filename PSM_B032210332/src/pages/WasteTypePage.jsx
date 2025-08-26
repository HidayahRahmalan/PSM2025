// WasteTypePage.jsx

import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Modal from 'react-bootstrap/Modal';
import Button from 'react-bootstrap/Button';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

const WasteTypePage = () => {
  const { wasteType } = useParams();
  const navigate = useNavigate();

  const [wasteData, setWasteData] = useState([]);
  const [allWasteTypes, setAllWasteTypes] = useState([]);
  const [filteredLocation, setFilteredLocation] = useState('all');
  const [locations, setLocations] = useState([]);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editEntry, setEditEntry] = useState(null);
  const [prevScrollPos, setPrevScrollPos] = useState(window.scrollY);
  const [navbarVisible, setNavbarVisible] = useState(true);
  const [alertMessage, setAlertMessage] = useState('');
  const [alertType, setAlertType] = useState('');
  const [showAlert, setShowAlert] = useState(false);
  const [isSorted, setIsSorted] = useState(false);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  const pageTitle = wasteType ? wasteType.charAt(0).toUpperCase() + wasteType.slice(1) : '';

  // --- START: MODIFICATION FOR BACKGROUND IMAGE ---
  useEffect(() => {
    // Apply styles to the body tag when the component mounts
    document.body.style.backgroundImage = "url('/Background image Website Data Sisa.png')";
    document.body.style.backgroundSize = 'cover';
    document.body.style.backgroundPosition = 'center center';
    document.body.style.backgroundRepeat = 'no-repeat';
    document.body.style.backgroundAttachment = 'fixed';
    document.body.style.minHeight = '100vh';
    document.body.style.width = '100vw';
    document.body.style.overflowX = 'hidden';
    
    // Cleanup function to remove styles when the component unmounts
    return () => {
      document.body.style.backgroundImage = '';
      document.body.style.backgroundSize = '';
      document.body.style.backgroundPosition = '';
      document.body.style.backgroundRepeat = '';
      document.body.style.backgroundAttachment = '';
      document.body.style.minHeight = '';
      document.body.style.width = '';
      document.body.style.overflowX = '';
    };
  }, []); // Empty dependency array ensures this runs only once on mount and cleanup on unmount
  // --- END: MODIFICATION FOR BACKGROUND IMAGE ---

  useEffect(() => {
    const handleScroll = () => {
      const currentScrollPos = window.scrollY;
      setNavbarVisible(prevScrollPos > currentScrollPos || currentScrollPos < 10);
      setPrevScrollPos(currentScrollPos);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [prevScrollPos]);

  const loadData = useCallback(async () => {
    if (!wasteType) return;
    try {
      const params = new URLSearchParams();
      params.append('location', filteredLocation);
      params.append('sort', isSorted);
      if (startDate) params.append('startDate', startDate);
      if (endDate) params.append('endDate', endDate);
      
      const res = await fetch(`http://localhost:3000/api/recyclable/${wasteType}?${params.toString()}`);
      const data = await res.json();
      setWasteData(data);
    } catch (err) {
      console.error(`Failed to load data for ${wasteType}:`, err);
    }
  }, [wasteType, filteredLocation, isSorted, startDate, endDate]);

  useEffect(() => {
    const fetchGlobalData = async () => {
      try {
        const resTypes = await fetch('http://localhost:3000/api/recyclable-types');
        const types = await resTypes.json();
        setAllWasteTypes(types);
      } catch (err) {
        console.error("Failed to fetch all waste types:", err);
      }
    };
    fetchGlobalData();
  }, []);

  useEffect(() => {
    const fetchLocationsForType = async () => {
      if (!wasteType) return;
      try {
        const resLocations = await fetch(`http://localhost:3000/api/recyclable-locations/${wasteType}`);
        const typeSpecificLocations = await resLocations.json();
        setLocations(typeSpecificLocations);
      } catch (err) {
        console.error(`Failed to fetch locations for ${wasteType}:`, err);
      }
    };
    
    setFilteredLocation('all');
    fetchLocationsForType();
  }, [wasteType]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const openEditModal = (row) => {
    setEditEntry(row);
    setShowEditModal(true);
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    if (!editEntry) return;
    const actualWeight = parseFloat(editEntry.rwaste_actual_weight);
    if (isNaN(actualWeight)) {
      alert("Please enter a valid number.");
      return;
    }
    try {
      const res = await fetch(`http://localhost:3000/api/recyclable/${wasteType}/${editEntry.rwasteid}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          rwaste_type: editEntry.rwaste_type,
          rwaste_actual_weight: actualWeight
        })
      });
      if (res.ok) {
        alert("Updated successfully!");
        setShowEditModal(false);
        loadData();
      } else {
        alert("Update failed.");
      }
    } catch (err) {
      console.error("Error updating:", err);
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm("Are you sure you want to delete this waste entry?")) return;
    try {
      const res = await fetch(`http://localhost:3000/api/recyclable/${wasteType}/${id}`, {
        method: 'DELETE',
      });
      if (res.ok) {
        setAlertMessage("Entry deleted successfully.");
        setAlertType("success");
        setShowAlert(true);
        loadData();
      } else {
        setAlertMessage("Failed to delete entry. Please try again.");
        setAlertType("danger");
        setShowAlert(true);
      }
    } catch (err) {
      console.error("Error deleting:", err);
      setAlertMessage("Network error. Please try again later.");
      setAlertType("danger");
      setShowAlert(true);
    }
    setTimeout(() => setShowAlert(false), 3000);
  };
  
  // UPDATED: This function now shows the filename for PDFs and an image preview for images.
  const renderMedia = (filename) => {
    if (!filename) {
      return <span>No File</span>;
    }
    
    const fileUrl = `http://20.205.132.62:3000/uploads/${filename}`;
    
    if (filename.toLowerCase().endsWith('.pdf')) {
      return (
        <a 
          href={fileUrl} 
          target="_blank" 
          rel="noopener noreferrer"
          title={`View ${filename}`}
          className="d-flex align-items-center text-decoration-none"
        >
          <i className="bi bi-file-earmark-pdf-fill me-2 fs-4 text-danger"></i>
          <span style={{ wordBreak: 'break-all' }}>{filename}</span>
        </a>
      );
    } else {
      // For images, keep the preview clickable to view full size in a new tab
      return (
        <a href={fileUrl} target="_blank" rel="noopener noreferrer" title={`View ${filename}`}>
            <img 
              src={fileUrl} 
              alt="Waste" 
              style={{ width: '100px', height: '100px', objectFit: 'cover' }} 
            />
        </a>
      );
    }
  };

  const renderTable = () => {
    const groupedData = wasteData.reduce((acc, cur) => {
        acc[cur.location_name] = [...(acc[cur.location_name] || []), cur];
        return acc;
    }, {});

    if (Object.keys(groupedData).length === 0) {
        return <div className="text-center p-4 bg-light rounded">No data available for the current selection.</div>
    }

    return Object.entries(groupedData).map(([loc, rows]) => {
      const totalActualWeight = rows.reduce((sum, row) => {
        const weight = parseFloat(row.rwaste_actual_weight);
        return sum + (isNaN(weight) ? 0 : weight);
      }, 0);
      
      const totalGhgReduction = (totalActualWeight * 586.5 / 1000);

      return (
        <div key={loc} className="mb-5">
          <h5>{loc}</h5>
          <table className="table table-bordered table-striped">
            <thead>
              <tr>
                <th>Image</th>
                <th>Waste Type</th>
                <th>Estimated Weight</th>
                <th>Actual Weight</th>
                <th>GHG Carbon Reduction (KgCO2)</th>
                <th>Collection Method</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const formattedDate = row.rwaste_datetaken ? new Date(row.rwaste_datetaken).toLocaleDateString() : '-';
                const actualWeight = parseFloat(row.rwaste_actual_weight);
                const ghgReduction = !isNaN(actualWeight) ? (actualWeight * 586.5 / 1000).toFixed(3) : '-';
                const filename = row.file_path ? row.file_path.split(/[\\/]/).pop() : '';
                
                return (
                  <tr key={row.rwasteid}>
                    <td>{renderMedia(filename)}</td>
                    <td>{row.rwaste_type}</td>
                    <td>{row.rwaste_estimated_weight} kg</td>
                    <td>{row.rwaste_actual_weight != null ? `${row.rwaste_actual_weight} kg` : ''}</td>
                    <td>{ghgReduction === '-' ? '-' : `${ghgReduction} KgCO2`}</td>
                    <td>{row.collection_method}</td>
                    <td>{formattedDate}</td>
                    <td className="text-center">
                      <i className="bi bi-pencil-square text-warning me-2" role="button" onClick={() => openEditModal(row)}></i>
                      <button className="btn text-danger p-0 border-0 bg-transparent" onClick={() => handleDelete(row.rwasteid)} title="Delete">
                        <i className="bi bi-trash-fill"></i>
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
          
          <div className="card bg-light mb-3">
            <div className="card-header fw-bold text-center">Summary for {loc}</div>
              <div className="card-body">
                <div className="row text-center">
                  <div className="col-md-6 border-end">
                      <h5 className="card-title">Total Actual Weight</h5>
                      <p className="card-text fs-4 fw-bold text-primary">{totalActualWeight.toFixed(2)} kg</p>
                  </div>
                  <div className="col-md-6">
                      <h5 className="card-title">Total GHG Carbon Reduction</h5>
                      <p className="card-text fs-4 fw-bold text-success">{totalGhgReduction.toFixed(3)} KgCO2</p>
                  </div>
                </div>
            </div>
          </div>
        </div>
      );
    });
  };
  
  const overlayStyle = { backgroundColor: 'rgba(29, 215, 128, 0.817)', padding: '50px', borderRadius: '10px' };
  const navStyle = { top: navbarVisible ? '0' : '-80px', transition: 'top 0.3s ease-in-out', position: 'fixed', width: '100%', zIndex: 1000 };
  const profileIconStyle = { color: '#f8f9fa', fontSize: '1.8rem', cursor: 'pointer', transition: 'color 0.3s' };

  return (
    <div>
      <nav className="navbar navbar-expand-lg navbar-dark bg-dark" style={navStyle}>
        <div className="container-fluid">
          <a className="navbar-brand" href="/Dashboard">CENSEI</a>
          <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span className="navbar-toggler-icon"></span></button>
          <div className="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul className="navbar-nav">
              <li className="nav-item"><a className="nav-link" href="/dashboard"><i className="bi bi-house-door-fill"></i></a></li>
              <li className="nav-item"><a className="nav-link" href="/Organic">Recyclable Organic & Inorganic</a></li>
              <li className="nav-item"><a className="nav-link" href="/Hazardous">Hazardous</a></li>
              <li className="nav-item dropdown">
                <a className="nav-link dropdown-toggle" href="/#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Data Entry</a>
                <ul className="dropdown-menu" aria-labelledby="dataEntryDropdown">
                  <li><a className="dropdown-item" href="/entry_organic">Recyclable Organic & Inorganic</a></li>
                  <li><a className="dropdown-item" href="/entry_ hazardous">Hazardous</a></li>
                </ul>
              </li>
              <li className="nav-item"><a className="nav-link" href="https://censei.utem.edu.my/index.php/ms/">About Us</a></li>
              <li className="nav-item d-flex align-items-center ms-3">
                <i className="bi bi-person-circle" style={profileIconStyle} data-bs-toggle="modal" data-bs-target="#profileModal" title="Profile"></i>
              </li>
            </ul>
          </div>
        </div>
      </nav>

      <div className="container my-5" style={{...overlayStyle, paddingTop: '25px', paddingBottom: '25px' }}>
        <h1 className="text-center mb-4">Recyclable Waste Data <i className="bi bi-recycle"></i> {pageTitle}</h1>

        {showAlert && <div className={`alert alert-${alertType} text-center`} role="alert">{alertMessage}</div>}

        <div className="mb-4">
          <div className="card bg-light p-3">
              <div className="row g-3 align-items-end">
                  <div className="col-lg-4 col-md-6">
                      <label htmlFor="location-select" className="form-label fw-bold">Location</label>
                      <select id="location-select" className="form-select" value={filteredLocation} onChange={e => setFilteredLocation(e.target.value)}>
                          <option value="all">All Locations</option>
                          {locations.map(loc => <option key={loc} value={loc}>{loc}</option>)}
                      </select>
                  </div>

                  <div className="col-lg-4 col-md-6">
                      <label className="form-label fw-bold">Date Range</label>
                      <div className="input-group">
                          <input type="date" className="form-control" value={startDate} onChange={e => setStartDate(e.target.value)} aria-label="Start Date" />
                          <span className="input-group-text">to</span>
                          <input type="date" className="form-control" value={endDate} onChange={e => setEndDate(e.target.value)} aria-label="End Date" />
                      </div>
                  </div>
                  
                  <div className="col-lg-4 col-md-12 d-flex justify-content-lg-end justify-content-center align-items-center flex-wrap mt-3 mt-lg-0">
                      <button className="btn btn-info me-2 mb-1" onClick={() => { setStartDate(''); setEndDate(''); setFilteredLocation('all'); }}><i className="bi bi-eraser-fill"></i> Clear Filters</button>
                      <button className="btn btn-secondary mb-1" onClick={() => navigate('/organic')}>Back</button>
                  </div>
              </div>
          </div>

          <div className="d-flex justify-content-end mt-3">
            <button className="btn btn-warning me-2" onClick={() => setIsSorted(true)}><i className="bi bi-sort-numeric-up"></i> Sort by Actual Weight</button>
            <button className="btn btn-outline-dark" onClick={() => setIsSorted(false)}><i className="bi bi-arrow-counterclockwise"></i> Reset Sort</button>
          </div>
        </div>

        {renderTable()}
      </div>

      <div className="modal fade" id="profileModal" tabIndex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div className="modal-dialog modal-lg modal-dialog-centered"><div className="modal-content bg-dark text-white"><div className="modal-header"><h5 className="modal-title" id="profileModalLabel">User Profile</h5><button type="button" className="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div className="modal-body p-0" style={{ height: '500px' }}><iframe src="/profile" width="100%" height="100%" style={{ border: 'none' }} title="User Profile"></iframe></div></div></div>
      </div>

      <Modal show={showEditModal} onHide={() => setShowEditModal(false)} centered>
        <Modal.Header closeButton><Modal.Title>Edit Waste Entry</Modal.Title></Modal.Header>
        <Modal.Body>
          <form onSubmit={handleEditSubmit}>
            <div className="mb-3">
              <label className="form-label">Waste Type</label>
              <select 
                className="form-select" 
                value={editEntry?.rwaste_type?.toLowerCase() || ''} 
                onChange={(e) => setEditEntry({ ...editEntry, rwaste_type: e.target.value })} 
                required
              >
                <option value="">-- Select Waste Type --</option>
                {allWasteTypes.map(type => (
                    <option key={type} value={type}>
                        {type.charAt(0).toUpperCase() + type.slice(1)}
                    </option>
                ))}
              </select>
            </div>
            <div className="mb-3">
              <label className="form-label">Actual Weight (kg)</label>
              <input className="form-control" type="text" pattern="^\d+(\.\d{1,2})?$" value={editEntry?.rwaste_actual_weight || ''} onChange={e => setEditEntry({ ...editEntry, rwaste_actual_weight: e.target.value })} required />
            </div>
            <Button type="submit" variant="success">Save Changes</Button>
          </form>
        </Modal.Body>
      </Modal>
    </div>
  );
};

export default WasteTypePage;