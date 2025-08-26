// Organic.jsx

import React, { useEffect, useState } from 'react';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';

const ICON_MAP = {
  plastic: 'bi-bag',
  paper: 'bi-file-earmark-text',
  metal: 'bi-tools',
  fabric: 'bi-scissors',
  'Others (Glasses)': 'bi-box', // Icon for the "glass/other" category
  'used cooking oil': 'bi-droplet', // Icon for "waste cooking oil"
  default: 'bi-recycle', // Fallback icon
};
const ORGANIC_TYPES = ['used cooking oil']; // "waste cooking oil" is the organic type

const Organic = () => {
  const [categories, setCategories] = useState({ inorganic: [], organic: [] });
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
        const response = await fetch('http://localhost:3000/api/recyclable-types');
        if (!response.ok) {
          throw new Error('Failed to fetch waste types from server.');
        }
        const typesFromDb = await response.json();

        const newCategories = {
          inorganic: [],
          organic: []
        };

        typesFromDb.forEach(type => {
          const typeKey = type.toLowerCase(); // Ensure consistent casing for lookups
          const categoryItem = {
            label: type.toUpperCase(),
            link: `/waste/${type}`, // Original type string for URL
            icon: ICON_MAP[typeKey] || ICON_MAP.default,
          };

          if (ORGANIC_TYPES.includes(typeKey)) {
            newCategories.organic.push(categoryItem);
          } else {
            newCategories.inorganic.push(categoryItem);
          }
        });

        setCategories(newCategories);
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

  // --- STYLES MODIFIED FOR FULL PAGE FIT ---
  const bodyStyle = {
    backgroundImage: "url('/Background image Website Data Sisa.png')",
    backgroundSize: 'cover',
    backgroundRepeat: 'no-repeat',
    backgroundAttachment: 'fixed',
    color: '#000',
    margin: 0,
    minHeight: '100vh',    // Make sure the main div takes full viewport height
    display: 'flex',       // Use flexbox for layout
    flexDirection: 'column', // Stack children vertically (navbar, content area)
  };

  const navStyle = {
    top: navbarVisible ? '0' : '-80px', // Adjust if navbar height is different
    transition: 'top 0.3s ease-in-out',
    position: 'fixed', // Keep navbar fixed at the top
    width: '100%',
    zIndex: 1000,
    height: '60px', // Explicitly set navbar height (adjust as needed)
  };

  // New wrapper for content to handle navbar spacing and growth
  const contentWrapperStyle = {
    paddingTop: navStyle.height, // Space for the fixed navbar
    flexGrow: 1,                 // Allow this wrapper to grow and fill remaining vertical space
    display: 'flex',             // Use flex to manage the green overlay's position
    flexDirection: 'column',     // Stack children of contentWrapper (like overlay)
    // overflowY: 'auto' // Add this if content within overlay itself needs to scroll
  };

  const overlayStyle = {
    backgroundColor: 'rgba(29, 215, 128, 0.817)',
    padding: '40px 50px', // Adjusted padding
    borderRadius: '10px',
    width: '100%',
    maxWidth: '1200px',  // Optional: Max width for the content area
    margin: 'auto',      // Center the overlay horizontally
                         // Vertical centering is handled by flexbox of contentWrapper if content is short
    display: 'flex',
    flexDirection: 'column',
    // justifyContent: 'center', // Remove if you want content to start from top of green box
                               // Keep if you want short content centered vertically in green box
  };

  const iconCardStyle = {
    textAlign: 'center',
    padding: '20px',
    borderRadius: '10px',
    backgroundColor: 'rgb(222, 209, 209)',
    color: '#000',
    transition: 'transform 0.2s, background-color 0.2s',
    height: '100%', // Make cards fill the height of their grid cell
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
    color: '#f8f9fa',
    fontSize: '1.8rem',
    cursor: 'pointer',
    transition: 'color 0.3s',
  };

  const renderContent = () => {
    if (loading) {
        return <div className="text-center py-5"><h3>Loading categories...</h3></div>;
    }
    if (error) {
        return <div className="alert alert-danger text-center py-5"><h3>Error: {error}</h3><p>Please ensure the backend server is running and the database is accessible.</p></div>;
    }
    const hasInorganic = categories.inorganic.length > 0;
    const hasOrganic = categories.organic.length > 0;

    if (!hasInorganic && !hasOrganic) {
        return <div className="text-center py-5"><h3>No waste categories found.</h3></div>;
    }

    return (
      <>
        {hasInorganic && (
          <>
            <h1 className="text-center mb-4">Inorganic Recyclable Waste Categories</h1>
            <div className="row g-4 mb-5 justify-content-center">
              {categories.inorganic.map((item, idx) => (
                <div className="col-12 col-sm-6 col-md-4 col-lg-3 d-flex" key={idx}>
                  <a href={item.link} className="text-decoration-none d-block w-100">
                    <div style={iconCardStyle} className="icon-card">
                      <i className={`bi ${item.icon}`} style={iconStyle}></i>
                      <div>{item.label}</div>
                    </div>
                  </a>
                </div>
              ))}
            </div>
          </>
        )}
        {hasOrganic && (
          <>
            <h1 className="text-center mb-4">Organic Recyclable Waste Categories</h1>
            <div className="row g-4 justify-content-center">
              {categories.organic.map((item, idx) => (
                <div className="col-12 col-sm-6 col-md-4 col-lg-3 d-flex" key={idx}>
                  <a href={item.link} className="text-decoration-none d-block w-100">
                    <div style={iconCardStyle} className="icon-card">
                      <i className={`bi ${item.icon}`} style={iconStyle}></i>
                      <div>{item.label}</div>
                    </div>
                  </a>
                </div>
              ))}
            </div>
          </>
        )}
      </>
    );
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
                <a className="nav-link dropdown-toggle" href="/#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Data Entry</a>
                <ul className="dropdown-menu" aria-labelledby="dataEntryDropdown">
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

      <div style={contentWrapperStyle}> {/* Content wrapper takes care of navbar spacing and growth */}
        <div className="container" style={overlayStyle}>
          {renderContent()}
        </div>
      </div>

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
    </div>
  );
};

export default Organic;