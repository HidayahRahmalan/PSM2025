import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import Modal from 'react-bootstrap/Modal';
import Button from 'react-bootstrap/Button';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

const wasteOptions = {
  "SW110": "BULB AND LAMPS",
  "SW305": "LUBRICANT",
  "SW306": "HYDRAULIC",
  "SW307": "TRANSFOMER OIL (NATURAL + MINERAL)",
  "SW322": "SANITIZING DISINFECTION",
  "SW403": "DISCARDED DRUGS",
  "SW404": "CLINICAL WASTE",
  "SW409": "EMPTY DISPOSED CONTAINER",
  "SW410": "CONTAMINATED RAGS, GLOVE",
  "SW430": "OBSOLETE LABORATORY CHEMICAL"
};

const Bulb = () => {
  const [wasteData, setWasteData] = useState([]);
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

  // State to manage the active summary modal's data. null means modal is closed.
  const [activeSummaryModal, setActiveSummaryModal] = useState(null);

  const navigate = useNavigate();

  useEffect(() => {
    const handleScroll = () => {
      const currentScrollPos = window.scrollY;
      setNavbarVisible(prevScrollPos > currentScrollPos || currentScrollPos < 10);
      setPrevScrollPos(currentScrollPos);
    };
    window.addEventListener('scroll', handleScroll);
    return () => window.removeEventListener('scroll', handleScroll);
  }, [prevScrollPos]);

  const loadWasteData = useCallback(async () => {
    try {
      const res = await fetch('http://localhost:3000/api/waste_bulb');
      const data = await res.json();
      setWasteData(data);
      setLocations([...new Set(data.map(item => item.location_name))]);
    } catch (err) {
      console.error('Failed to load data:', err);
    }
  }, []);

  const loadSortedWasteData = useCallback(async () => {
    try {
      const locQuery = filteredLocation === 'all' ? '' : `?location=${encodeURIComponent(filteredLocation)}`;
      // NOTE: Assumes a new backend endpoint '/api/waste_bulb_sorted' exists for sorting
      const res = await fetch(`http://localhost:3000/api/waste_bulb_sorted${locQuery}`);
      const data = await res.json();
      setWasteData(data);
    } catch (err) {
      console.error('Failed to load sorted data:', err);
    }
  }, [filteredLocation]);

  useEffect(() => {
    if (isSorted) {
      loadSortedWasteData();
    } else {
      loadWasteData();
    }
  }, [filteredLocation, isSorted, loadSortedWasteData, loadWasteData]);

  const openEditModal = (row) => {
    setEditEntry({
      ...row,
      hwaste_code: row.hwaste_code,
      hwaste_type: row.hwaste_type,
      hwaste_actual_weight: row.hwaste_actual_weight || ''
    });
    setShowEditModal(true);
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    const { hwasteid, hwaste_code, hwaste_type, hwaste_actual_weight } = editEntry;
    const actualWeight = parseFloat(hwaste_actual_weight);

    if (!hwaste_code || isNaN(actualWeight)) {
      alert("Please select a valid waste code and enter a valid number.");
      return;
    }

    try {
      const res = await fetch(`http://localhost:3000/api/waste_bulb/${hwasteid}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          hwaste_code,
          hwaste_type,
          hwaste_actual_weight: actualWeight
        }),
      });

      if (res.ok) {
        alert("Updated successfully!");
        setShowEditModal(false);
        if (isSorted) {
          loadSortedWasteData();
        } else {
          loadWasteData();
        }
      } else {
        alert("Update failed.");
      }
    } catch (err) {
      console.error("Error updating:", err);
    }
  };

  const handleDelete = async (id) => {
    const confirmed = window.confirm("Are you sure you want to delete this waste entry?");
    if (!confirmed) return;

    try {
      const res = await fetch(`http://localhost:3000/api/waste_bulb/${id}`, {
        method: 'DELETE',
      });

      if (res.ok) {
        setAlertMessage("Entry deleted successfully.");
        setAlertType("success");
        setShowAlert(true);
        if (isSorted) {
          loadSortedWasteData();
        } else {
          loadWasteData();
        }
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

  const calculateBulbGHG = (row) => {
    const isBulb = row.hwaste_code === 'SW110';
    const weight = parseFloat(row.hwaste_actual_weight);
    if (!isBulb || isNaN(weight)) {
      return 0;
    }
    const emissionFactor = 0.5; // kg CO₂e per kg of bulb waste (representative value for treatment)
    return weight * emissionFactor;
  };

  const openSummaryModal = (loc, rows) => {
    const bulbRows = rows.filter(row =>
      row.hwaste_code === 'SW110' && !isNaN(parseFloat(row.hwaste_actual_weight))
    );
    const totalWeightInKg = bulbRows.reduce((sum, row) => sum + parseFloat(row.hwaste_actual_weight), 0);
    const totalWeightInTonnes = totalWeightInKg / 1000;
    const totalGHG = bulbRows.reduce((sum, row) => sum + calculateBulbGHG(row), 0);
    const monthlyGHG = bulbRows.reduce((acc, row) => {
      if (row.hwaste_datetaken) {
        const ghg = calculateBulbGHG(row);
        const monthKey = new Date(row.hwaste_datetaken).toISOString().slice(0, 7); // 'YYYY-MM'
        acc[monthKey] = (acc[monthKey] || 0) + ghg;
      }
      return acc;
    }, {});
    const sortedMonths = Object.keys(monthlyGHG).sort();
    setActiveSummaryModal({ loc, totalGHG, monthlyGHG, sortedMonths, totalWeightInTonnes });
  };

  const closeSummaryModal = () => setActiveSummaryModal(null);

  const renderTable = () => {
    const groupedData = filteredLocation === 'all'
      ? wasteData.reduce((acc, cur) => {
          acc[cur.location_name] = [...(acc[cur.location_name] || []), cur];
          return acc;
        }, {})
      : { [filteredLocation]: wasteData.filter(item => item.location_name === filteredLocation) };

    return Object.entries(groupedData).map(([loc, rows]) => {
      
      const totalActualWeight = rows.reduce((sum, row) => {
        const weight = parseFloat(row.hwaste_actual_weight);
        return sum + (isNaN(weight) ? 0 : weight);
      }, 0);

      return (
        <div key={loc} className="mb-5">
          <h5>{loc}</h5>
          <table className="table table-bordered table-striped">
            <thead>
              <tr>
                <th>Image</th>
                <th>Waste Name</th>
                <th>Waste Code</th>
                <th>Waste Type</th>
                <th>Estimated Weight</th>
                <th>Actual Weight</th>
                <th>Management Method</th>
                <th>GHG Emissions (kg CO₂e)</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => {
                const ghg = calculateBulbGHG(row);
                const formattedGHG = ghg !== 0 ? ghg.toFixed(2) : '-';
                return (
                  <tr key={row.hwasteid}>
                    <td><img src={row.hwaste_photo} alt="Bulb Waste" width="100" height="100" /></td>
                    <td>{row.hwaste_name}</td>
                    <td>{row.hwaste_code}</td>
                    <td>{row.hwaste_type}</td>
                    <td>{row.hwaste_estimated_weight} kg</td>
                    <td>{row.hwaste_actual_weight != null ? `${row.hwaste_actual_weight} kg` : ''}</td>
                    <td>{row.storage_management}</td>
                    <td className={ghg > 0 ? 'text-danger fw-bold' : ''}>
                      {formattedGHG === '-' ? '-' : `${formattedGHG} kg`}
                    </td>
                    <td className="text-center">
                      <i className="bi bi-pencil-square text-warning me-2" role="button" onClick={() => openEditModal(row)}></i>
                      <button className="btn text-danger p-0 border-0 bg-transparent" onClick={() => handleDelete(row.hwasteid)} title="Delete">
                        <i className="bi bi-trash-fill"></i>
                      </button>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          <div className="card bg-light mb-3">
            <div className="card-header fw-bold text-center">
              Weight Summary for {loc}
            </div>
            <div className="card-body text-center">
              <h5 className="card-title">Total Actual Weight</h5>
              <p className="card-text fs-4 fw-bold text-primary">
                {totalActualWeight.toFixed(2)} kg
              </p>
            </div>
          </div>

          <div className="text-start mb-3">
            <Button variant="info" onClick={() => openSummaryModal(loc, rows)}>
              <i className="bi bi-bar-chart-line-fill me-2"></i>
              GHG Emission Summary (Bulbs)
            </Button>
          </div>
        </div>
      );
    });
  };

  const bodyStyle = {
    backgroundImage: "url('/bg1.jpg')",
    backgroundSize: 'cover',
    backgroundPosition: 'center center',
    backgroundRepeat: 'no-repeat',
    backgroundAttachment: 'fixed',
    minHeight: '100vh',
    width: '100vw',
    overflowX: 'hidden',
    margin: 0,
    padding: 0,
    display: 'flex',
    flexDirection: 'column',
  };

  const overlayStyle = {
    backgroundColor: 'rgba(29, 215, 128, 0.817)',
    padding: '40px',
    borderRadius: '10px',
  };
  
  const navStyle = {
    top: navbarVisible ? '0' : '-80px',
    transition: 'top 0.3s ease-in-out',
    position: 'fixed',
    width: '100%',
    zIndex: 1000,
  };

  const profileIconStyle = {
    color: '#f8f9fa',
    fontSize: '1.8rem',
    cursor: 'pointer',
    transition: 'color 0.3s',
  };

  return (
    <div style={bodyStyle}>
      <nav className="navbar navbar-expand-lg navbar-dark bg-dark" style={navStyle}>
        <div className="container-fluid">
          <a className="navbar-brand" href="/Dashboard">CENSEI</a>
          <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span className="navbar-toggler-icon"></span>
          </button>
          <div className="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul className="navbar-nav">
              <li className="nav-item"><a className="nav-link" href="/dashboard"><i className="bi bi-house-door-fill"></i></a></li>
              <li className="nav-item"><a className="nav-link" href="/Organic">Recyclable Organic & Inorganic</a></li>
              <li className="nav-item"><a className="nav-link" href="/Hazardous">Hazardous</a></li>
              <li className="nav-item dropdown">
                <a className="nav-link dropdown-toggle" href="/#" data-bs-toggle="dropdown">Data Entry</a>
                <ul className="dropdown-menu">
                  <li><a className="dropdown-item" href="/entry_organic">Recyclable Organic & Inorganic</a></li>
                  <li><a className="dropdown-item" href="/entry_hazardous">Hazardous</a></li>
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

      <div className="container my-5" style={overlayStyle}>
        <h1 className="text-center mb-4">Hazardous Waste Data <i className="bi bi-lightbulb-fill"></i> Bulb & Lamps</h1>

        {showAlert && (
          <div className={`alert alert-${alertType} text-center`} role="alert">
            {alertMessage}
          </div>
        )}

        <div className="d-flex justify-content-between mb-3">
          <div className="input-group w-50">
            <select className="form-select" value={filteredLocation} onChange={e => setFilteredLocation(e.target.value)}>
              <option value="all">All Locations</option>
              {locations.map(loc => (
                <option key={loc} value={loc}>{loc}</option>
              ))}
            </select>
          </div>
          <div>
            <button className="btn btn-outline-warning me-2" onClick={() => setIsSorted(true)}>
              <i className="bi bi-sort-numeric-up"></i> Sort by Actual Weight
            </button>
            <button className="btn btn-outline-secondary me-2" onClick={() => setIsSorted(false)}>
              <i className="bi bi-arrow-counterclockwise"></i> Reset Sort
            </button>
            <button className="btn btn-secondary" onClick={() => navigate('/Hazardous')}>Back</button>
          </div>
        </div>

        {renderTable()}
      </div>

      {/* Profile Modal */}
      <div className="modal fade" id="profileModal" tabIndex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div className="modal-dialog modal-lg modal-dialog-centered">
          <div className="modal-content bg-dark text-white">
            <div className="modal-header">
              <h5 className="modal-title" id="profileModalLabel">User Profile</h5>
              <button type="button" className="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div className="modal-body p-0" style={{ height: '500px' }}>
              <iframe src="/profile" width="100%" height="100%" style={{ border: 'none' }} title="User Profile"></iframe>
            </div>
          </div>
        </div>
      </div>

      {/* Edit Modal */}
      <Modal show={showEditModal} onHide={() => setShowEditModal(false)} centered>
        <Modal.Header closeButton>
          <Modal.Title>Edit Waste Entry</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <form onSubmit={handleEditSubmit}>
            <div className="mb-3">
              <label className="form-label">Waste Code</label>
              <select
                className="form-select"
                value={editEntry?.hwaste_code || ''}
                onChange={(e) => {
                  const selectedCode = e.target.value;
                  setEditEntry({
                    ...editEntry,
                    hwaste_code: selectedCode,
                    hwaste_type: wasteOptions[selectedCode] || ''
                  });
                }}
                required
              >
                <option value="" disabled>Select waste code</option>
                {Object.entries(wasteOptions).map(([code, label]) => (
                  <option key={code} value={code}>
                    {code} - {label}
                  </option>
                ))}
              </select>
            </div>
            <div className="mb-3">
              <label className="form-label">Actual Weight (kg)</label>
              <input
                className="form-control"
                type="text"
                pattern="^\d+(\.\d{1,2})?$"
                value={editEntry?.hwaste_actual_weight || ''}
                onChange={(e) => setEditEntry({ ...editEntry, hwaste_actual_weight: e.target.value })}
                required
              />
            </div>
            <Button type="submit" variant="success">Save Changes</Button>
          </form>
        </Modal.Body>
      </Modal>

      {/* GHG Summary Modal */}
      <Modal show={activeSummaryModal !== null} onHide={closeSummaryModal} centered>
        <Modal.Header closeButton>
          <Modal.Title>GHG Emission Summary for {activeSummaryModal?.loc}</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          {activeSummaryModal && (
            <table className="table table-sm table-borderless mb-0">
              {activeSummaryModal.sortedMonths.length > 0 && (
                <thead>
                  <tr>
                    <th className="ps-4">Month</th>
                    <th className="text-end pe-4">GHG Emissions</th>
                  </tr>
                </thead>
              )}
              <tbody>
                {activeSummaryModal.sortedMonths.length > 0 ? (
                  activeSummaryModal.sortedMonths.map(month => (
                    <tr key={month}>
                      <td className="ps-4">
                        {new Date(`${month}-02`).toLocaleString('default', { month: 'long', year: 'numeric' })}
                      </td>
                      <td className="text-end pe-4">
                        {activeSummaryModal.monthlyGHG[month].toFixed(2)} kg CO₂e
                      </td>
                    </tr>
                  ))
                ) : (
                  <tr>
                    <td colSpan="2" className="text-center text-muted">
                      No monthly data available for bulbs at this location.
                    </td>
                  </tr>
                )}
                <tr>
                  <td colSpan="2"><hr className="my-2" /></td>
                </tr>
                <tr>
                  <td className="ps-4">EMISSIONS FACTOR (kgCO2e/tonne)</td>
                  <td className="text-end pe-4">{(0.5 * 1000).toFixed(2)}</td>
                </tr>
                <tr>
                  <td className="ps-4">Total Amount Treated (ton)</td>
                  <td className="text-end pe-4">
                    {activeSummaryModal.totalWeightInTonnes.toFixed(2)}
                  </td>
                </tr>
                <tr style={{ fontSize: '1.2rem' }}>
                  <td className="fw-bold ps-4">GHG (kgCO2e)</td>
                  <td className={`text-end pe-4 fw-bold ${activeSummaryModal.totalGHG > 0 ? 'text-danger' : ''}`}>
                    {activeSummaryModal.totalGHG.toFixed(2)}
                  </td>
                </tr>
              </tbody>
            </table>
          )}
        </Modal.Body>
        <Modal.Footer>
          <Button variant="secondary" onClick={closeSummaryModal}>
            Close
          </Button>
        </Modal.Footer>
      </Modal>

    </div>
  );
};

export default Bulb;