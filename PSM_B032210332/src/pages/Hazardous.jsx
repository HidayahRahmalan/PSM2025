// Hazardous.jsx

import React, { useEffect, useState } from 'react';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

// Mapping from waste codes to icons and labels
const ICON_MAP = {
    'SW110': { icon: 'bi-lightbulb', label: 'BULB AND LAMPS' },
    'SW305': { icon: 'bi-droplet-half', label: 'LUBRICANT' },
    'SW306': { icon: 'bi-tools', label: 'HYDRAULIC' },
    'SW307': { icon: 'bi-plug-fill', label: 'TRANSFORMER OIL' },
    'SW322': { icon: 'bi-shield-plus', label: 'SANITIZING & DISINFECTION' },
    'SW403': { icon: 'bi-capsule', label: 'DISCARDED DRUGS' },
    'SW404': { icon: 'bi-bandaid-fill', label: 'CLINICAL WASTE' },
    'SW409': { icon: 'bi-box-seam', label: 'EMPTY DISPOSED CONTAINER' },
    'SW410': { icon: 'bi-hand-index-fill', label: 'CONTAMINATED RAGS, GLOVE' },
    'SW430': { icon: 'bi-exclamation-triangle-fill', label: 'OBSOLETE LAB CHEMICAL' },
    'default': { icon: 'bi-radioactive', label: 'HAZARDOUS' }
};

const Hazardous = () => {
    const [categories, setCategories] = useState([]);
    const [prevScrollPos, setPrevScrollPos] = useState(window.scrollY);
    const [navbarVisible, setNavbarVisible] = useState(true);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
        const handleScroll = () => {
            const currentScrollPos = window.scrollY;
            setNavbarVisible(prevScrollPos > currentScrollPos || currentScrollPos < 10);
            setPrevScrollPos(currentScrollPos);
        };
        window.addEventListener('scroll', handleScroll);
        return () => window.removeEventListener('scroll', handleScroll);
    }, [prevScrollPos]);

    useEffect(() => {
        const fetchCategories = async () => {
            try {
                setLoading(true);
                const response = await fetch('http://localhost:3000/api/hazardous-types');
                if (!response.ok) {
                    throw new Error('Failed to fetch hazardous waste types.');
                }
                const typesFromDb = await response.json();

                const categoryItems = typesFromDb.map(type => ({
                    code: type.hwaste_code,
                    label: ICON_MAP[type.hwaste_code]?.label || type.hwaste_type,
                    link: `/hazardous-waste/${type.hwaste_code}`,
                    icon: ICON_MAP[type.hwaste_code]?.icon || ICON_MAP.default.icon,
                }));

                setCategories(categoryItems);
                setError(null);
            } catch (err) {
                console.error(err);
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };
        fetchCategories();
    }, []);

    // --- STYLES ---
    const bodyStyle = {
        minHeight: '100vh',
        display: 'flex',
        flexDirection: 'column',
        backgroundImage: "url('/Background image Website Data Sisa.png')",
        backgroundSize: 'cover',
        backgroundRepeat: 'no-repeat',
        backgroundAttachment: 'fixed',
        backgroundPosition: 'center',
        color: 'black',
        margin: 0,
        paddingTop: '80px', // Space for navbar
    };

    const overlayStyle = {
        backgroundColor: 'rgba(29, 215, 128, 0.817)',
        padding: '50px',
        borderRadius: '10px',
        flexGrow: 1,
    };

    const navStyle = {
        top: navbarVisible ? '0' : '-80px',
        transition: 'top 0.3s ease-in-out',
        position: 'fixed',
        width: '100%',
        zIndex: 1000,
    };

    const iconCardStyle = {
        textAlign: 'center',
        padding: '20px',
        borderRadius: '10px',
        backgroundColor: 'rgb(222, 209, 209)',
        color: 'black',
        transition: 'transform 0.2s, background-color 0.2s',
        height: '100%',
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'center',
        alignItems: 'center',
    };

    const iconStyle = {
        fontSize: '3rem',
        marginBottom: '10px',
    };
    
    const profileIconStyle = {
        fontSize: '1.8rem',
        color: '#f8f9fa',
        cursor: 'pointer',
    };

    const renderContent = () => {
        if (loading) {
            return <div className="text-center py-5"><h3>Loading categories...</h3></div>;
        }
        if (error) {
            return <div className="alert alert-danger text-center py-5"><h3>Error: {error}</h3><p>Please ensure the backend server is running and the database is accessible.</p></div>;
        }
        if (categories.length === 0) {
            return <div className="text-center py-5"><h3>No hazardous waste categories found.</h3></div>;
        }

        return (
            <div className="row g-4 justify-content-center">
                {categories.map((item) => (
                    <div className="col-12 col-sm-6 col-md-4 col-lg-3 d-flex" key={item.code}>
                        <a href={item.link} className="text-decoration-none d-block w-100">
                            <div style={iconCardStyle} className="icon-card">
                                <i className={`bi ${item.icon}`} style={iconStyle}></i>
                                <div className="fw-bold">{item.label}</div>
                                <div className="text-muted small">({item.code})</div>
                            </div>
                        </a>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <div style={bodyStyle}>
            {/* Navigation Bar */}
            <nav className="navbar navbar-expand-lg navbar-dark bg-dark" style={navStyle}>
                <div className="container-fluid">
                    <a className="navbar-brand" href="/dashboard">CENSEI</a>
                    <button className="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span className="navbar-toggler-icon"></span></button>
                    <div className="collapse navbar-collapse justify-content-end" id="navbarNav">
                        <ul className="navbar-nav">
                            <li className="nav-item"><a className="nav-link" href="/dashboard"><i className="bi bi-house-door-fill"></i></a></li>
                            <li className="nav-item"><a className="nav-link" href="/organic">Recyclable Organic & Inorganic</a></li>
                            <li className="nav-item"><a className="nav-link" href="/hazardous">Hazardous</a></li>
                            <li className="nav-item dropdown">
                                <a className="nav-link dropdown-toggle" href="/#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Data Entry</a>
                                <ul className="dropdown-menu">
                                    <li><a className="dropdown-item" href="/entry_organic">Recyclable Organic & Inorganic</a></li>
                                    <li><a className="dropdown-item" href="/entry_hazardous">Hazardous</a></li>
                                </ul>
                            </li>
                            <li className="nav-item"><a className="nav-link" href="https://censei.utem.edu.my/index.php/ms/" target="_blank" rel="noopener noreferrer">About Us</a></li>
                            <li className="nav-item d-flex align-items-center ms-3">
                                <i className="bi bi-person-circle" style={profileIconStyle} data-bs-toggle="modal" data-bs-target="#profileModal" title="Profile"></i>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>

            {/* Main Content */}
            <div className="container my-5" style={overlayStyle}>
                <h1 className="text-center mb-5">Hazardous Waste Categories</h1>
                {renderContent()}
            </div>

            {/* Profile Modal */}
            <div className="modal fade" id="profileModal" tabIndex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
                <div className="modal-dialog modal-lg modal-dialog-centered"><div className="modal-content bg-dark text-white"><div className="modal-header"><h5 className="modal-title" id="profileModalLabel">User Profile</h5><button type="button" className="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div className="modal-body p-0" style={{ height: '500px' }}><iframe src="/profile" width="100%" height="100%" style={{ border: 'none' }} title="User Profile"></iframe></div></div></div>
            </div>
        </div>
    );
};

export default Hazardous;