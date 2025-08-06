import React from 'react';
import { Link } from 'react-router-dom';

const LandingPage: React.FC = () => {
  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-900 via-purple-900 to-gray-900 text-white overflow-hidden">
      {/* Animated background elements */}
      <div className="absolute inset-0 overflow-hidden">
        <div className="absolute -top-40 -right-40 w-80 h-80 bg-orange-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div className="absolute -bottom-40 -left-40 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div className="absolute top-40 left-40 w-80 h-80 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      {/* Navigation */}
      <nav className="relative z-10 flex justify-between items-center px-6 py-4">
        <div className="flex items-center space-x-2">
          <img 
            src="/img/favicon/android-chrome-512x512.png" 
            alt="Hysterka Logo" 
            className="w-12 h-12 animate-pulse"
          />
          <span className="text-2xl font-bold bg-gradient-to-r from-orange-400 to-orange-600 bg-clip-text text-transparent">
            Hysterka
          </span>
        </div>
        <div className="flex space-x-4">
          <Link 
            to="/login" 
            className="px-4 py-2 text-gray-300 hover:text-white transition-colors duration-200"
          >
            Sign In
          </Link>
          <Link 
            to="/register" 
            className="px-4 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg transition-colors duration-200 font-medium"
          >
            Get Started
          </Link>
        </div>
      </nav>

      {/* Hero Section */}
      <div className="relative z-10 flex flex-col items-center justify-center min-h-screen px-6 text-center">
        <div className="max-w-4xl mx-auto">
          {/* Main Logo */}
          <div className="mb-8 animate-bounce">
            <img 
              src="/img/favicon/android-chrome-512x512.png" 
              alt="Hysterka Logo" 
              className="w-32 h-32 mx-auto drop-shadow-2xl"
            />
          </div>

          {/* Main Heading */}
          <h1 className="text-6xl md:text-8xl font-bold mb-6 animate-fade-in">
            <span className="bg-gradient-to-r from-orange-400 via-orange-500 to-orange-600 bg-clip-text text-transparent">
              Hysterka
            </span>
          </h1>

          {/* Subtitle */}
          <p className="text-xl md:text-2xl text-gray-300 mb-8 animate-fade-in animation-delay-500">
            Websites for you and your projects.
          </p>
          <p className="text-lg md:text-xl text-gray-400 mb-12 animate-fade-in animation-delay-1000">
            Hosted directly from your repository. Just edit, push, and your changes are live.
          </p>

          {/* CTA Buttons */}
          <div className="flex flex-col sm:flex-row gap-4 justify-center items-center animate-fade-in animation-delay-1500">
            <Link 
              to="/register" 
              className="px-8 py-4 bg-orange-500 hover:bg-orange-600 rounded-lg text-lg font-semibold transition-all duration-200 transform hover:scale-105 shadow-lg hover:shadow-xl"
            >
              Get Started
            </Link>
            <Link 
              to="/login" 
              className="px-8 py-4 border-2 border-gray-600 hover:border-gray-500 rounded-lg text-lg font-semibold transition-all duration-200 transform hover:scale-105"
            >
              Sign In
            </Link>
          </div>
        </div>
      </div>

      {/* Features Section */}
      <div className="relative z-10 py-20 px-6">
        <div className="max-w-6xl mx-auto">
          <h2 className="text-4xl font-bold text-center mb-16 animate-fade-in animation-delay-2000">
            Ready to get started?
          </h2>
          
          <div className="grid md:grid-cols-3 gap-8">
            {/* Feature 1 */}
            <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:border-orange-500 transition-all duration-300 transform hover:scale-105 animate-fade-in animation-delay-2500">
              <div className="w-16 h-16 bg-orange-500 rounded-lg flex items-center justify-center mb-6">
                <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold mb-4">Multi-User Environment</h3>
              <p className="text-gray-400">Create your account and start managing your own crawler searches with personalized dashboards.</p>
            </div>

            {/* Feature 2 */}
            <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:border-orange-500 transition-all duration-300 transform hover:scale-105 animate-fade-in animation-delay-3000">
              <div className="w-16 h-16 bg-purple-500 rounded-lg flex items-center justify-center mb-6">
                <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold mb-4">Smart Crawling</h3>
              <p className="text-gray-400">Automated web scraping with intelligent parsing, image downloading, and availability checking.</p>
            </div>

            {/* Feature 3 */}
            <div className="bg-gray-800/50 backdrop-blur-sm rounded-xl p-8 border border-gray-700 hover:border-orange-500 transition-all duration-300 transform hover:scale-105 animate-fade-in animation-delay-3500">
              <div className="w-16 h-16 bg-pink-500 rounded-lg flex items-center justify-center mb-6">
                <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-5 5v-5zM4 19h6v-2H4v2zM4 15h6v-2H4v2zM4 11h6V9H4v2zM4 7h6V5H4v2zM10 7h10V5H10v2zM10 11h10V9H10v2zM10 15h10v-2H10v2zM10 19h10v-2H10v2z" />
                </svg>
              </div>
              <h3 className="text-xl font-semibold mb-4">Real-time Notifications</h3>
              <p className="text-gray-400">Get instant alerts for new items, price drops, and weekly summaries via email and push notifications.</p>
            </div>
          </div>
        </div>
      </div>

      {/* Footer */}
      <footer className="relative z-10 py-8 px-6 border-t border-gray-800">
        <div className="max-w-6xl mx-auto flex flex-col md:flex-row justify-between items-center">
          <div className="flex items-center space-x-2 mb-4 md:mb-0">
            <img 
              src="/img/favicon/android-chrome-192x192.png" 
              alt="Hysterka Logo" 
              className="w-8 h-8"
            />
            <span className="text-lg font-semibold bg-gradient-to-r from-orange-400 to-orange-600 bg-clip-text text-transparent">
              Hysterka
            </span>
          </div>
          <div className="text-gray-400 text-sm">
            © 2024 Hysterka. Built with ❤️ for web crawlers.
          </div>
        </div>
      </footer>
    </div>
  );
};

export default LandingPage; 