// App.js

import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Login from './pages/login';
import Dashboard from './pages/Dashboard';
import Organic from './pages/Organic';
import WasteTypePage from './pages/WasteTypePage';
import Hazardous from './pages/Hazardous'; // <-- This will be the new Hazardous landing page
import HazardousWastePage from './pages/HazardousWastePage'; // <-- This is the new dynamic page
import EntryOrganic from './pages/EntryOrganic';
import EntryHazardous from './pages/Entryhazardous';
import Profile from './pages/Profile';
import Register from './pages/Register';

function App() {
  return (
    <Router>
      <Routes>
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/organic" element={<Organic />} />
        <Route path="/entry_organic" element={<EntryOrganic />} />
        <Route path="/entry_hazardous" element={<EntryHazardous />} />
        <Route path="/profile" element={<Profile />} />
        <Route path="/register" element={<Register />} />
        
        {/* === Recyclable Waste Route === */}
        <Route path="/waste/:wasteType" element={<WasteTypePage />} />

        {/* === NEW Hazardous Waste Routes === */}
        <Route path="/hazardous" element={<Hazardous />} />
        <Route path="/hazardous-waste/:wasteCode" element={<HazardousWastePage />} />
        
        {/* === Default/Login Route === */}
        <Route path="/" element={<Login />} />
      </Routes>
    </Router>
  );
}

export default App;