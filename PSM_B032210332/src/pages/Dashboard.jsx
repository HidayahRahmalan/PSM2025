import React, { useEffect, useState } from 'react';
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import {
  BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
  PieChart, Pie, Cell, Legend
} from 'recharts';
// Import Modal and Button from react-bootstrap
import { Modal, Button } from 'react-bootstrap';


// A constant for calculating label position
const RADIAN = Math.PI / 180;

// Custom function to render percentage labels on the pie chart
const renderCustomizedLabel = ({ cx, cy, midAngle, innerRadius, outerRadius, percent, index }) => {
  // Don't render a label for very small slices to avoid clutter
  if (percent < 0.03) {
    return null;
  }

  // Calculate the position for the label slightly outside the pie
  const radius = outerRadius + 25;
  const x = cx + radius * Math.cos(-midAngle * RADIAN);
  const y = cy + radius * Math.sin(-midAngle * RADIAN);

  return (
    <text
      x={x}
      y={y}
      fill="black"
      textAnchor={x > cx ? 'start' : 'end'} // Smart alignment based on position
      dominantBaseline="central"
      fontSize={14}
    >
      {`${(percent * 100).toFixed(0)}%`}
    </text>
  );
};

// Helper function to capitalize the first letter of each word
const capitalizeWords = (str) => {
  if (!str) return '';
  return str.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

// Custom component for multi-line axis labels
const CustomAxisTick = ({ x, y, payload }) => {
  if (!payload || !payload.value) {
    return null;
  }

  // Split the name string like "SW110 Bulb And Lamps" into code and type
  const parts = payload.value.split(' ');
  const code = parts[0]; // e.g., "SW110"
  const type = parts.slice(1).join(' '); // e.g., "Bulb And Lamps"

  // Render two lines of text using SVG <text> elements.
  // The first <text> element with a smaller 'dy' will be positioned higher.
  return (
    <g transform={`translate(${x},${y})`}>
      <text x={0} y={0} dy={16} textAnchor="middle" fill="#666" fontSize={12} fontWeight="bold">
        {code}
      </text>
      <text x={0} y={0} dy={30} textAnchor="middle" fill="#666" fontSize={12}>
        {type}
      </text>
    </g>
  );
};


function Dashboard() {
  const [prevScrollPos, setPrevScrollPos] = useState(window.scrollY);
  const [navbarVisible, setNavbarVisible] = useState(true);
  const [username, setUsername] = useState(null);
  const [loading, setLoading] = useState(true);

  const [recyclableMonthly, setRecyclableMonthly] = useState([]);
  const [hazardousMonthly, setHazardousMonthly] = useState([]);
  const [recyclablePie, setRecyclablePie] = useState([]);
  const [hazardousPie, setHazardousPie] = useState([]);

  const [availableYears, setAvailableYears] = useState([]);
  const [recycleYear, setRecycleYear] = useState(null);
  const [hazardYear, setHazardYear] = useState(null);

  // --- Start of New Code for Introduction Editing ---
  const [introduction, setIntroduction] = useState(
    "The <strong>Sustainable Waste Management System</strong> is a centralized platform designed to monitor and manage various types of waste generated in an institution or community. It provides a user-friendly dashboard for tracking monthly waste collection, identifying trends, and promoting eco-friendly practices. With real-time visualization tools such as bar and pie charts, administrators and environmental officers can make informed decisions to support sustainability and regulatory compliance."
  );
  const [showEditIntroModal, setShowEditIntroModal] = useState(false);
  const [editedIntroduction, setEditedIntroduction] = useState(introduction);

  const handleShowEditIntroModal = () => {
    setEditedIntroduction(introduction); // Reset to current introduction when opening
    setShowEditIntroModal(true);
  };
  const handleCloseEditIntroModal = () => setShowEditIntroModal(false);
  const handleSaveIntroduction = () => {
    setIntroduction(editedIntroduction);
    handleCloseEditIntroModal();
  };
  // --- End of New Code ---


  const pieColorMap = {
      'fabric': 'pink',
      'paper': 'blue',
      'plastic': 'orange',
      'metal': 'brown',
      'glass': 'black',
      'other': 'black',
      'used cooking oil': 'purple',
  };
  const defaultPieColor = '#8884d8';

  const HAZARDOUS_COLORS = ['#0088FE', '#00C49F', '#FFBB28', '#FF8042', '#8A2BE2', '#DC143C'];

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
    const storedUsername = sessionStorage.getItem('username');
    const storedUserID = sessionStorage.getItem('userID');
    if (!storedUserID) {
      window.location.href = '/';
    } else {
      setUsername(storedUsername);
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const fetchYears = async () => {
      try {
        const res = await fetch('http://localhost:3000/api/available-years');
        const years = await res.json();
        setAvailableYears(years);
        if (years.length > 0) {
          const latestYear = Math.max(...years);
          setRecycleYear(latestYear);
          setHazardYear(latestYear);
        }
      } catch (err) {
        console.error('Error fetching years:', err);
      }
    };
    fetchYears();
  }, []);

  useEffect(() => {
    if (!recycleYear) return;
    const fetchRecyclableData = async () => {
      try {
        const [monthlyRes, pieRes] = await Promise.all([
          fetch(`http://localhost:3000/api/monthly-recyclable?year=${recycleYear}`),
          fetch(`http://localhost:3000/api/pie-recyclable?year=${recycleYear}`)
        ]);

        const monthlyData = await monthlyRes.json();
        const pieData = await pieRes.json();

        setRecyclableMonthly(monthlyData.map(d => ({
          month: d.month,
          organic: parseFloat(d.organic_weight),
          inorganic: parseFloat(d.inorganic_weight)
        })));

        setRecyclablePie(pieData.map(d => ({
          name: capitalizeWords(d.type),
          value: parseFloat(d.total)
        })));
      } catch (err) {
        console.error('Recyclable data fetch error:', err);
      }
    };
    fetchRecyclableData();
  }, [recycleYear]);


  useEffect(() => {
    if (!hazardYear) return;
    const fetchHazardousData = async () => {
      try {
        const [barDataRes, pieDataRes] = await Promise.all([
          fetch(`http://localhost:3000/api/monthly-hazardous?year=${hazardYear}`).then(res => res.json()),
          fetch(`http://localhost:3000/api/pie-hazardous?year=${hazardYear}`).then(res => res.json())
        ]);

        // Ensure the name is formatted with code first for the split logic to work.
        setHazardousMonthly(barDataRes.map(d => ({
          name: `${d.code} ${capitalizeWords(d.type)}`,
          total_weight: parseFloat(d.total_weight)
        })));

        setHazardousPie(pieDataRes.map(d => ({
          name: `${d.code} ${capitalizeWords(d.type)}`,
          value: parseFloat(d.total)
        })));
      } catch (err) {
        console.error('Hazardous fetch error:', err);
      }
    };
    fetchHazardousData();
  }, [hazardYear]);

  const styles = {
    body: {
      backgroundImage: "url('/Background image Website Data Sisa.png')",
      backgroundSize: 'cover',
      backgroundRepeat: 'no-repeat',
      backgroundAttachment: 'fixed',
      color: 'black',
      margin: 0,
      paddingTop: '100px',
    },
    overlay: {
      backgroundColor: 'rgba(29, 215, 128, 0.817)',
      padding: '20px',
      borderRadius: '10px',
    },
    chart: {
      backgroundColor: 'white',
      padding: '20px',
      borderRadius: '12px',
      boxShadow: '0 0 15px rgba(0, 0, 0, 0.2)',
    },
    nav: {
      top: navbarVisible ? '0' : '-80px',
      transition: 'top 0.3s ease-in-out',
      position: 'fixed',
      width: '100%',
      zIndex: 1000,
    },
    profileIcon: {
      fontSize: '1.8rem',
      cursor: 'pointer',
      color: 'white',
    }
  };

  if (loading || !recycleYear || !hazardYear) {
    return (
      <div className="text-center mt-5">
        <div className="spinner-border text-success" role="status">
          <span className="visually-hidden">Loading...</span>
        </div>
        <p>Loading dashboard...</p>
      </div>
    );
  }

  return (
    <div style={styles.body}>
      <nav className="navbar navbar-expand-lg navbar-dark bg-dark" style={styles.nav}>
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
                <a className="nav-link dropdown-toggle" href="/#" id="dataEntryDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  Data Entry
                </a>
                <ul className="dropdown-menu">
                  <li><a className="dropdown-item" href="/entry_organic">Recyclable Organic & Inorganic</a></li>
                  <li><a className="dropdown-item" href="/entry_hazardous">Hazardous</a></li>
                </ul>
              </li>
              <li className="nav-item"><a className="nav-link" href="https://censei.utem.edu.my/index.php/ms/" target="_blank" rel="noopener noreferrer">About Us</a></li>
              <li className="nav-item d-flex align-items-center ms-3">
                <i className="bi bi-person-circle" style={styles.profileIcon} role="button" data-bs-toggle="modal" data-bs-target="#profileModal" title="Profile" />
              </li>
            </ul>
          </div>
        </div>
      </nav>

      <div className="container main-content" style={styles.overlay}>
        <h1 className="text-center">Sustainable Waste Management System</h1>
        <hr className="bg-light" />
        <div className="text-end mb-4 text-muted small">Logged in as: {username}</div>

        <div className="mb-5 p-4 rounded shadow" style={{ backgroundColor: '#c0c0c0' }}>
          <h2 className="text-center mb-3">
            <i className="bi bi-info-circle-fill me-2 text-primary"></i>
            Introduction
          </h2>
          {/* Updated Introduction Section */}
          <p
            className="text-justify"
            style={{ fontSize: '1.05rem', lineHeight: '1.7', textAlign: 'justify' }}
            dangerouslySetInnerHTML={{ __html: introduction }}
          />
          {/* Add Edit Button Here */}
          <div className="text-end">
            <Button variant="primary" onClick={handleShowEditIntroModal}>
              Edit Introduction
            </Button>
          </div>
        </div>

        {/* --- Organic & Inorganic --- */}
        <div className="d-flex justify-content-end align-items-center mb-2">
          <label className="me-2 fw-bold">Select Year:</label>
          <select className="form-select w-auto" value={recycleYear} onChange={e => setRecycleYear(Number(e.target.value))}>
            {availableYears.map(year => (
              <option key={year} value={year}>{year}</option>
            ))}
          </select>
        </div>
        <h2 className="text-center mb-4">Recyclable Organic & Inorganic Waste ({recycleYear})</h2>
        <div className="row justify-content-center mb-5">
          <div className="col-md-8 mb-4">
            <div style={styles.chart}>
              <h5 className="text-center mb-3">
                Monthly Total Weight
              </h5>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={recyclableMonthly}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="month" />
                  <YAxis label={{ value: 'Weight (kg)', angle: -90, position: 'insideLeft' }} />
                  <Tooltip formatter={(value, name) => [`${value.toFixed(2)} kg`, name]} />
                  <Legend />
                  <Bar dataKey="organic" stackId="a" name="Organic" fill="blue" />
                  <Bar dataKey="inorganic" stackId="a" name="Inorganic" fill="purple" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
          <div className="col-md-8 mb-4">
            <div style={styles.chart}>
              <h5 className="text-center mb-3">Percentage Recyclable Waste</h5>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={recyclablePie}
                    dataKey="value"
                    nameKey="name"
                    outerRadius={100}
                    labelLine={false}
                    label={renderCustomizedLabel}
                  >
                    {recyclablePie.map((entry, index) => (
                      <Cell
                        key={`recycle-${index}`}
                        fill={pieColorMap[entry.name.toLowerCase()] || defaultPieColor}
                      />
                    ))}
                  </Pie>
                  <Legend layout="vertical" verticalAlign="middle" align="right" wrapperStyle={{ paddingLeft: '20px' }} />
                  <Tooltip formatter={(value, name) => [`${value.toFixed(2)} kg`, name]} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>

        {/* --- Hazardous Waste --- */}
        <div className="d-flex justify-content-end align-items-center mb-2">
          <label className="me-2 fw-bold">Select Year:</label>
          <select className="form-select w-auto" value={hazardYear} onChange={e => setHazardYear(Number(e.target.value))}>
            {availableYears.map(year => (
              <option key={year} value={year}>{year}</option>
            ))}
          </select>
        </div>
        <h2 className="text-center mb-4">Hazardous Waste ({hazardYear})</h2>
        <div className="row justify-content-center mb-5">
          <div className="col-md-8 mb-4">
            <div style={styles.chart}>
              <h5 className="text-center mb-3">
                Total Weight by Type
              </h5>
              <ResponsiveContainer width="100%" height={400}>
                <BarChart data={hazardousMonthly} margin={{ top: 5, right: 30, left: 20, bottom: 80 }}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" interval={0} tick={<CustomAxisTick />} />
                  <YAxis label={{ value: 'Weight (kg)', angle: -90, position: 'insideLeft' }} />
                  <Tooltip formatter={(value, name, props) => [`${props.payload.total_weight.toFixed(2)} kg`, props.payload.name]} />
                  <Bar dataKey="total_weight" fill="#dc3545" name="Total Weight" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </div>
          <div className="col-md-8 mb-4">
            <div style={styles.chart}>
              <h5 className="text-center mb-3">Percentage  Hazardous Waste </h5>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={hazardousPie}
                    dataKey="value"
                    nameKey="name"
                    outerRadius={100}
                    labelLine={false}
                    label={renderCustomizedLabel}
                  >
                    {hazardousPie.map((entry, index) => (
                      <Cell key={`hazard-${index}`} fill={HAZARDOUS_COLORS[index % HAZARDOUS_COLORS.length]} />
                    ))}
                  </Pie>
                  <Legend layout="vertical" verticalAlign="middle" align="right" wrapperStyle={{ paddingLeft: '20px' }} />
                  <Tooltip formatter={(value, name) => [`${value.toFixed(2)} kg`, name]} />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </div>
        </div>
      </div>

      {/* --- New Edit Introduction Modal --- */}
      <Modal show={showEditIntroModal} onHide={handleCloseEditIntroModal} centered>
        <Modal.Header closeButton>
          <Modal.Title>Edit Introduction</Modal.Title>
        </Modal.Header>
        <Modal.Body>
          <textarea
            className="form-control"
            rows="10"
            value={editedIntroduction.replace(/<[^>]*>?/gm, '')} // Basic HTML tag removal for editing
            onChange={(e) => setEditedIntroduction(e.target.value)}
          />
        </Modal.Body>
        <Modal.Footer>
          <Button variant="secondary" onClick={handleCloseEditIntroModal}>
            Close
          </Button>
          <Button variant="primary" onClick={handleSaveIntroduction}>
            Save Changes
          </Button>
        </Modal.Footer>
      </Modal>

      {/* Profile Modal */}
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

export default Dashboard;