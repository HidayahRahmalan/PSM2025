// HazardousWastePage.jsx

import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import Modal from 'react-bootstrap/Modal';
import Button from 'react-bootstrap/Button';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

const HazardousWastePage = () => {
    const { wasteCode } = useParams();
    const navigate = useNavigate();

    const [wasteData, setWasteData] = useState([]);
    const [allWasteCodes, setAllWasteCodes] = useState([]);
    const [filteredLocation, setFilteredLocation] = useState('all');
    const [locations, setLocations] = useState([]);
    const [showEditModal, setShowEditModal] = useState(false);
    const [editEntry, setEditEntry] = useState(null);
    const [navbarVisible, setNavbarVisible] = useState(true);
    const [alertMessage, setAlertMessage] = useState('');
    const [alertType, setAlertType] = useState('');
    const [showAlert, setShowAlert] = useState(false);
    const [isSorted, setIsSorted] = useState(false);
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [prevScrollPos, setPrevScrollPos] = useState(window.scrollY);

    const pageTitle = allWasteCodes.find(c => c.hwaste_code === wasteCode)?.hwaste_type || wasteCode || '';

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
        if (!wasteCode) return;
        try {
            const params = new URLSearchParams({
                location: filteredLocation,
                sort: isSorted,
                ...(startDate && { startDate }),
                ...(endDate && { endDate }),
            });
            const res = await fetch(`http://localhost:3000/api/hazardous/${wasteCode}?${params.toString()}`);
            const data = await res.json();
            setWasteData(data);
        } catch (err) {
            console.error(`Failed to load data for ${wasteCode}:`, err);
        }
    }, [wasteCode, filteredLocation, isSorted, startDate, endDate]);

    useEffect(() => {
        const fetchGlobalData = async () => {
            try {
                const res = await fetch('http://localhost:3000/api/hazardous-types');
                setAllWasteCodes(await res.json());
            } catch (err) {
                console.error("Failed to fetch all hazardous waste codes:", err);
            }
        };
        fetchGlobalData();
    }, []);

    useEffect(() => {
        const fetchLocationsForCode = async () => {
            if (!wasteCode) return;
            try {
                const res = await fetch(`http://localhost:3000/api/hazardous-locations/${wasteCode}`);
                setLocations(await res.json());
            } catch (err) {
                console.error(`Failed to fetch departments for ${wasteCode}:`, err);
            }
        };
        setFilteredLocation('all');
        fetchLocationsForCode();
    }, [wasteCode]);

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

        try {
            const res = await fetch(`http://localhost:3000/api/hazardous/${wasteCode}/${editEntry.hwasteid}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    hwaste_code: editEntry.hwaste_code,
                    hwaste_type: editEntry.hwaste_type,
                    hwaste_actual_weight: parseFloat(editEntry.hwaste_actual_weight),
                }),
            });
            if (res.ok) {
                setAlertMessage("Updated successfully!"); setAlertType("success");
                setShowEditModal(false);
                loadData();
            } else {
                setAlertMessage("Update failed."); setAlertType("danger");
            }
        } catch (err) {
            console.error("Error updating:", err);
            setAlertMessage("Network error during update."); setAlertType("danger");
        }
        setShowAlert(true);
        setTimeout(() => setShowAlert(false), 3000);
    };

    const handleDelete = async (id) => {
        if (!window.confirm("Are you sure you want to delete this entry?")) return;
        try {
            const res = await fetch(`http://localhost:3000/api/hazardous/${wasteCode}/${id}`, { method: 'DELETE' });
            if (res.ok) {
                setAlertMessage("Entry deleted successfully."); setAlertType("success");
                loadData();
            } else {
                setAlertMessage("Failed to delete entry."); setAlertType("danger");
            }
        } catch (err) {
            console.error("Error deleting:", err);
            setAlertMessage("Network error during deletion."); setAlertType("danger");
        }
        setShowAlert(true);
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
            const locationKey = cur.department_name || "Unspecified Department";
            acc[locationKey] = [...(acc[locationKey] || []), cur];
            return acc;
        }, {});

        if (Object.keys(groupedData).length === 0) return <div className="text-center p-4 bg-light rounded">No data available for the current selection.</div>;

        return Object.entries(groupedData).map(([loc, rows]) => (
            <div key={loc} className="mb-5">
                <h5>{loc}</h5>
                <table className="table table-bordered table-striped">
                    <thead><tr><th>Image</th><th>Waste Name</th><th>Code</th><th>Estimated Weight</th><th>Actual Weight</th><th>Department</th><th>Actions</th></tr></thead>
                    <tbody>
                        {rows.map((row) => {
                            const filename = row.file_path ? row.file_path.split(/[\\/]/).pop() : '';
                            return (
                                <tr key={row.hwasteid}>
                                    <td>{renderMedia(filename)}</td>
                                    <td>{row.hwaste_name}</td>
                                    <td>{row.hwaste_code}</td>
                                    <td>{row.hwaste_estimated_weight} kg</td>
                                    <td>{row.hwaste_actual_weight != null ? `${row.hwaste_actual_weight} kg` : ''}</td>
                                    <td>{row.department_name}</td>
                                    <td className="text-center"><i className="bi bi-pencil-square text-warning me-2" role="button" onClick={() => openEditModal(row)}></i><button className="btn text-danger p-0 border-0 bg-transparent" onClick={() => handleDelete(row.hwasteid)} title="Delete"><i className="bi bi-trash-fill"></i></button></td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
                <div className="d-flex justify-content-end">
                    <div className="card bg-light w-50"><div className="card-body text-center p-2"><h6 className="card-title mb-1">Total Actual Weight for {loc}</h6><p className="card-text fs-5 fw-bold text-primary mb-0">{rows.reduce((sum, r) => sum + (parseFloat(r.hwaste_actual_weight) || 0), 0).toFixed(2)} kg</p></div></div>
                </div>
            </div>
        ));
    };

    const bodyStyle = { backgroundImage: "url('/Background image Website Data Sisa.png')", backgroundSize: 'cover', backgroundAttachment: 'fixed', minHeight: '100vh', width: '100vw', overflowX: 'hidden', paddingTop: '80px'};
    const overlayStyle = { backgroundColor: 'rgba(29, 215, 128, 0.817)', padding: '50px', borderRadius: '10px' };
    const navStyle = { top: navbarVisible ? '0' : '-80px', transition: 'top 0.3s', position: 'fixed', width: '100%', zIndex: 1000 };
    const profileIconStyle = { color: '#f8f9fa', fontSize: '1.8rem', cursor: 'pointer' };

    return (
        <div style={bodyStyle}>
            <nav className="navbar navbar-expand-lg navbar-dark bg-dark" style={navStyle}>
                <div className="container-fluid"><a className="navbar-brand" href="/Dashboard">CENSEI</a><button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span className="navbar-toggler-icon"></span></button><div className="collapse navbar-collapse justify-content-end" id="navbarNav"><ul className="navbar-nav"><li className="nav-item"><a className="nav-link" href="/dashboard"><i className="bi bi-house-door-fill"></i></a></li><li className="nav-item"><a className="nav-link" href="/Organic">Recyclable Organic & Inorganic</a></li><li className="nav-item"><a className="nav-link" href="/Hazardous">Hazardous</a></li><li className="nav-item dropdown"><a className="nav-link dropdown-toggle" href="/#" role="button" data-bs-toggle="dropdown">Data Entry</a><ul className="dropdown-menu"><li><a className="dropdown-item" href="/entry_organic">Recyclable Organic & Inorganic</a></li><li><a className="dropdown-item" href="/entry_hazardous">Hazardous</a></li></ul></li><li className="nav-item"><a className="nav-link" href="https://censei.utem.edu.my/index.php/ms/">About Us</a></li><li className="nav-item d-flex align-items-center ms-3"><i className="bi bi-person-circle" style={profileIconStyle} data-bs-toggle="modal" data-bs-target="#profileModal"></i></li></ul></div></div>
            </nav>

            <div className="container my-5" style={overlayStyle}>
                <h1 className="text-center mb-4">Hazardous Waste: {pageTitle}</h1>
                {showAlert && <div className={`alert alert-${alertType} text-center`}>{alertMessage}</div>}

                <div className="card bg-light p-3 mb-3"><div className="row g-3 align-items-end">
                    <div className="col-lg-4 col-md-6">
                        <label className="form-label fw-bold">Department</label>
                        <select className="form-select" value={filteredLocation} onChange={e => setFilteredLocation(e.target.value)}>
                            <option value="all">All Departments</option>
                            {locations.map(dept => <option key={dept.departmentid} value={dept.departmentid}>{dept.department_name}</option>)}
                        </select>
                    </div>
                    <div className="col-lg-4 col-md-6"><label className="form-label fw-bold">Date Range</label><div className="input-group"><input type="date" className="form-control" value={startDate} onChange={e => setStartDate(e.target.value)} /><span className="input-group-text">to</span><input type="date" className="form-control" value={endDate} onChange={e => setEndDate(e.target.value)} /></div></div>
                    <div className="col-lg-4 col-md-12 d-flex justify-content-lg-end justify-content-center align-items-center mt-3 mt-lg-0"><button className="btn btn-info me-2 mb-1" onClick={() => { setStartDate(''); setEndDate(''); setFilteredLocation('all'); }}><i className="bi bi-eraser-fill"></i> Clear</button><button className="btn btn-secondary mb-1" onClick={() => navigate('/hazardous')}>Back</button></div>
                </div></div>
                <div className="d-flex justify-content-end mb-3"><button className="btn btn-warning me-2" onClick={() => setIsSorted(true)}><i className="bi bi-sort-numeric-up"></i> Sort by Weight</button><button className="btn btn-outline-dark" onClick={() => setIsSorted(false)}><i className="bi bi-arrow-counterclockwise"></i> Reset Sort</button></div>

                {renderTable()}
            </div>

            <div className="modal fade" id="profileModal"><div className="modal-dialog modal-lg modal-dialog-centered"><div className="modal-content bg-dark text-white"><div className="modal-header"><h5 className="modal-title">User Profile</h5><button type="button" className="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div className="modal-body p-0" style={{ height: '500px' }}><iframe src="/profile" width="100%" height="100%" style={{ border: 'none' }} title="Profile"></iframe></div></div></div></div>

            <Modal show={showEditModal} onHide={() => setShowEditModal(false)} centered>
                <Modal.Header closeButton><Modal.Title>Edit Waste Entry</Modal.Title></Modal.Header>
                <Modal.Body><form onSubmit={handleEditSubmit}>
                    <div className="mb-3"><label className="form-label">Waste Code</label><select className="form-select" value={editEntry?.hwaste_code || ''} onChange={e => setEditEntry({ ...editEntry, hwaste_code: e.target.value, hwaste_type: allWasteCodes.find(c => c.hwaste_code === e.target.value)?.hwaste_type || '' })} required><option value="">-- Select Code --</option>{allWasteCodes.map(c => <option key={c.hwaste_code} value={c.hwaste_code}>{c.hwaste_code} - {c.hwaste_type}</option>)}</select></div>
                    <div className="mb-3"><label className="form-label">Actual Weight (kg)</label><input className="form-control" type="text" pattern="^\d+(\.\d{1,2})?$" value={editEntry?.hwaste_actual_weight || ''} onChange={e => setEditEntry({ ...editEntry, hwaste_actual_weight: e.target.value })} required /></div>
                    <Button type="submit" variant="success">Save Changes</Button>
                </form></Modal.Body>
            </Modal>
        </div>
    );
};

export default HazardousWastePage;