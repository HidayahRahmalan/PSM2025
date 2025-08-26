import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';

const Login = () => {
  const [username, setUsername] = useState('');
  const [userPassword, setUserPassword] = useState('');
  const [message, setMessage] = useState('');
  const [messageColor, setMessageColor] = useState('#f0f0f0');
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();

    try {
      const response = await fetch('http://localhost:3000/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ username, user_password: userPassword }),
      });

      const data = await response.json();

      if (response.ok) {
        setMessageColor('#d4edda');
        setMessage(`✅ ${data.message}`);
        // Store both userID and username from the successful response
        sessionStorage.setItem('userID', data.user.userid);
        sessionStorage.setItem('username', data.user.username);

        setTimeout(() => {
          navigate('/dashboard');
        }, 1000);
      } else {
        setMessageColor('#f8d7da');
        setMessage(`❌ ${data.error}`);
      }
    } catch (err) {
      setMessageColor('#f8d7da');
      setMessage('❌ Failed to connect to server');
      console.error('Error:', err);
    }
  };

  // This <style> tag allows us to use pseudo-selectors like ::placeholder and :hover
  const customStyles = `
    .login-input::placeholder {
      color: rgba(255, 255, 255, 0.7);
      opacity: 1; /* Override Firefox's default opacity */
    }

    .register-link {
      color: #ffffff;
      transition: color 0.3s ease; /* Smooth transition for hover effect */
    }

    .register-link:hover {
      color: #61dafb; /* Bright blue color on hover */
    }
  `;

  // Styles
  const pageStyle = {
    margin: 0,
    fontFamily: "'Segoe UI', sans-serif",
    backgroundImage: "url('/Background image Website Data Sisa.png')",
    // 'contain' ensures the ENTIRE image is visible. It scales the image to fit
    // within the screen without cropping any logos or text.
    backgroundSize: 'contain',
    // 'no-repeat' is essential to prevent the image from tiling.
    backgroundRepeat: 'no-repeat',
    // 'center' keeps the image perfectly centered.
    backgroundPosition: 'center',
    height: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    // A dark background color fills any empty space (letterboxing) gracefully.
    backgroundColor: '#000000',
    padding: '1rem', // Adds space on small screens.
  };

  const boxStyle = {
    background: 'rgba(110, 150, 110, 0.9)',
    padding: '2.5rem',
    borderRadius: '15px',
    boxShadow: '0 8px 32px 0 rgba(0, 0, 0, 0.37)',
    // These properties make the login box flexible and responsive.
    width: '90%',
    maxWidth: '500px',
    textAlign: 'center',
    border: '1px solid rgba(255, 255, 255, 0.3)',
  };
  
  const inputStyle = {
    margin: '10px 0',
    padding: '12px',
    width: '100%',
    boxSizing: 'border-box',
    borderRadius: '8px',
    border: '1px solid rgba(255, 255, 255, 0.2)',
    background: 'rgba(0, 0, 0, 0.2)',
    color: '#ffffff',
    fontSize: '1rem',
  };

  const buttonStyle = {
    padding: '12px 10px',
    width: '100%',
    backgroundColor: '#28a745',
    color: 'white',
    border: 'none',
    borderRadius: '8px',
    cursor: 'pointer',
    fontWeight: 'bold',
    fontSize: '1.1rem',
    boxShadow: '0 4px 8px rgba(0,0,0,0.15)',
    transition: 'transform 0.1s ease',
  };

  const registerLinkStyle = {
    width: '100%',
    background: 'none',
    border: 'none',
    fontFamily: 'inherit',
    fontSize: '0.9rem',
    textDecoration: 'underline',
    cursor: 'pointer',
    padding: '10px 0 0 0',
  };

  const buttonGroupStyle = {
    marginTop: '1.5rem',
    display: 'flex',
    flexDirection: 'column',
    alignItems: 'center',
    gap: '12px',
  };

  return (
    <div style={pageStyle}>
      <style>{customStyles}</style>
      <div style={boxStyle}>
        <h1 style={{ fontSize: '1.8rem', marginBottom: '0.5rem', color: 'black' }}>
          Sustainable Waste Management System
        </h1>
        <h2 style={{ marginTop: '0', borderBottom: '1px solid rgba(255, 255, 255, 0.2)', paddingBottom: '1rem', fontWeight: 'normal', color: '#f0f0f0' }}>
          Admin
        </h2>
        <form onSubmit={handleSubmit}>
          <input
            type="text"
            placeholder="Username"
            required
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            style={inputStyle}
            className="login-input" 
          />
          <input
            type="password"
            placeholder="Password"
            required
            value={userPassword}
            onChange={(e) => setUserPassword(e.target.value)}
            style={inputStyle}
            className="login-input" 
          />
          <div style={buttonGroupStyle}>
            <button type="submit" style={buttonStyle}>Login</button>
            <button
              type="button"
              onClick={() => navigate('/register')}
              style={registerLinkStyle}
              className="register-link" 
            >
              Register User
            </button>
          </div>
        </form>
        <div style={{ color: messageColor, marginTop: '15px', fontWeight: 'bold' }}>
          {message}
        </div>
      </div>
    </div>
  );
};

export default Login;