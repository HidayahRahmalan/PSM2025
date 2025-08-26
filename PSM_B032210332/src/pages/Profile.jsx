import React, { useEffect, useState } from 'react';
import Modal from 'react-bootstrap/Modal';
import Button from 'react-bootstrap/Button';
import 'bootstrap/dist/css/bootstrap.min.css';

const Profile = () => {
  const [user, setUser] = useState(null);
  const [error, setError] = useState('');
  
  // State for the update modal
  const [showUpdateModal, setShowUpdateModal] = useState(false);
  const [formData, setFormData] = useState({ user_email: '', user_phonenumber: '' });
  
  // State for success/error messages
  const [updateMessage, setUpdateMessage] = useState({ type: '', text: '' });
  
  const userID = sessionStorage.getItem('userID');

  useEffect(() => {
    if (!userID) {
      setError('No user ID found in session.');
      return;
    }

    const fetchUser = async () => {
      try {
        const res = await fetch(`http://localhost:3000/users/${userID}`);
        const data = await res.json();

        if (res.ok) {
          setUser(data);
          // Initialize form data for the modal
          setFormData({
            user_email: data.user_email,
            user_phonenumber: data.user_phonenumber,
          });
        } else {
          setError(data.error || 'Failed to fetch user.');
        }
      } catch (err) {
        setError('Error connecting to server.');
      }
    };

    fetchUser();
  }, [userID]);
  
  // --- Modal and Update Handlers ---

  const handleShowModal = () => {
    // Reset form data to current user state every time modal opens
    setFormData({
      user_email: user.user_email,
      user_phonenumber: user.user_phonenumber,
    });
    setUpdateMessage({ type: '', text: '' }); // Clear previous messages
    setShowUpdateModal(true);
  };
  
  const handleCloseModal = () => setShowUpdateModal(false);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData(prevData => ({
      ...prevData,
      [name]: value,
    }));
  };

  const handleUpdateSubmit = async (e) => {
    e.preventDefault();
    setUpdateMessage({ type: '', text: '' }); // Clear previous messages

    try {
      const res = await fetch(`http://localhost:3000/users/${userID}`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      const data = await res.json();

      if (res.ok) {
        setUser(data); // Update user state with new data
        handleCloseModal(); // Close the modal on success
        setUpdateMessage({ type: 'success', text: 'Profile updated successfully!' }); // Show success on main page
      } else {
        // Show error message inside the modal
        setUpdateMessage({ type: 'danger', text: data.error || 'Failed to update profile.' });
      }
    } catch (err) {
      setUpdateMessage({ type: 'danger', text: 'Error connecting to server.' });
    }
  };
  
  const handleLogout = () => {
    sessionStorage.clear();
    window.top.location.href = '/';
  };

  // --- Render Logic ---

  if (error) {
    return (
      <div className="alert alert-danger m-4" role="alert">
        {error}
      </div>
    );
  }

  if (!user) {
    return (
      <div className="text-center my-5">
        <div className="spinner-border text-success" role="status">
          <span className="visually-hidden">Loading user profile...</span>
        </div>
        <div className="mt-2">Loading user profile...</div>
      </div>
    );
  }

  return (
    <>
      <div className="container p-4 position-relative">
        <h2 className="mb-4 text-center">User Profile</h2>
        
        {/* Success message display area */}
        {updateMessage.text && updateMessage.type === 'success' && (
          <div className={`alert alert-${updateMessage.type} text-center`} role="alert">
            {updateMessage.text}
          </div>
        )}

        <table className="table table-striped table-bordered">
          <tbody>
            <tr><th>User ID</th><td>{user.userid}</td></tr>
            <tr><th>Full Name</th><td>{user.user_name}</td></tr>
            <tr><th>Email</th><td>{user.user_email}</td></tr>
            <tr><th>Phone Number</th><td>{user.user_phonenumber}</td></tr>
            <tr><th>Username</th><td>{user.username}</td></tr>
            <tr><th>Role</th><td>{user.user_role}</td></tr>
            <tr><th>Position</th><td>{user.user_position}</td></tr>
          </tbody>
        </table>

        <div className="d-flex justify-content-between mt-4">
          <Button variant="primary" onClick={handleShowModal}>
            Update Profile
          </Button>
          <Button variant="danger" onClick={handleLogout}>
            Logout
          </Button>
        </div>
      </div>

      {/* Update Profile Modal */}
      <Modal show={showUpdateModal} onHide={handleCloseModal} centered>
        <Modal.Header closeButton>
          <Modal.Title>Update Email & Phone</Modal.Title>
        </Modal.Header>
        <form onSubmit={handleUpdateSubmit}>
          <Modal.Body>
            {/* Error message display area inside the modal */}
            {updateMessage.text && updateMessage.type === 'danger' && (
              <div className="alert alert-danger" role="alert">
                {updateMessage.text}
              </div>
            )}
            <div className="mb-3">
              <label htmlFor="user_email" className="form-label">Email Address</label>
              <input
                type="email"
                className="form-control"
                id="user_email"
                name="user_email"
                value={formData.user_email}
                onChange={handleInputChange}
                required
              />
            </div>
            <div className="mb-3">
              <label htmlFor="user_phonenumber" className="form-label">Phone Number</label>
              <input
                type="tel"
                className="form-control"
                id="user_phonenumber"
                name="user_phonenumber"
                value={formData.user_phonenumber || ''}
                onChange={handleInputChange}
              />
            </div>
          </Modal.Body>
          <Modal.Footer>
            <Button variant="secondary" onClick={handleCloseModal}>
              Cancel
            </Button>
            <Button variant="success" type="submit">
              Save Changes
            </Button>
          </Modal.Footer>
        </form>
      </Modal>
    </>
  );
};

export default Profile;