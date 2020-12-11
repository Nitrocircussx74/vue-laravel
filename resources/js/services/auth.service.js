import axios from 'axios';

const API_URL = '/api/';

class AuthService {
  login(user) {
    return axios
      .post(API_URL + 'login', {
        email: user.email,
        password: user.password
      })
      .then(response => {
        if (response.data.access_token) {
          localStorage.setItem('user', JSON.stringify(response.data.user));
          localStorage.setItem('token', response.data.access_token);
        }
        return response.data.user;
      });
  }
  logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
  }
}

export default new AuthService();
