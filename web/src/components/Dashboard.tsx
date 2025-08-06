import React, { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useNavigate } from 'react-router-dom';

interface Ad {
  id: string;
  title: string;
  price: string;
  date: string;
  found_at?: string;
  query: string;
  images: string[];
  htmlPath: string;
  description: string;
  contact: string;
  location?: string;
  view_count?: string;
  category?: string;
  is_available?: boolean;
  last_updated?: string;
}

const Dashboard: React.FC = () => {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [ads, setAds] = useState<Ad[]>([]);
  const [filteredAds, setFilteredAds] = useState<Ad[]>([]);
  const [filter, setFilter] = useState('all');
  const [selectedAd, setSelectedAd] = useState<Ad | null>(null);
  const [isMobileDetailView, setIsMobileDetailView] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAds();
  }, []);

  useEffect(() => {
    if (filter === 'all') {
      setFilteredAds(ads);
    } else {
      setFilteredAds(ads.filter(ad => ad.query === filter));
    }
  }, [ads, filter]);

  const fetchAds = async () => {
    try {
      const response = await fetch('/data/index.json');
      const data = await response.json();
      setAds(data.ads || []);
    } catch (error) {
      console.error('Failed to fetch ads:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = async () => {
    await logout();
    navigate('/');
  };

  const parseLocation = (location: string) => {
    const parts = location.split(',');
    return {
      city: parts[0]?.trim() || '',
      region: parts[1]?.trim() || ''
    };
  };

  const formatFoundDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('sk-SK', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const createMapUrl = (city: string) => {
    return `https://www.google.com/maps/search/${encodeURIComponent(city)}`;
  };

  const formatRelativeTime = (foundAt: string) => {
    const now = new Date();
    const found = new Date(foundAt);
    const diffInMinutes = Math.floor((now.getTime() - found.getTime()) / (1000 * 60));
    
    if (diffInMinutes < 1) return 'práve teraz';
    if (diffInMinutes < 60) return `pred ${diffInMinutes} min`;
    if (diffInMinutes < 1440) return `pred ${Math.floor(diffInMinutes / 60)} hod`;
    return `pred ${Math.floor(diffInMinutes / 1440)} dňami`;
  };

  const handleImageClick = (index: number) => {
    if (selectedAd && selectedAd.images[index]) {
      window.open(selectedAd.images[index], '_blank');
    }
  };

  const handleAdSelect = (ad: Ad) => {
    setSelectedAd(ad);
    if (window.innerWidth < 1024) {
      setIsMobileDetailView(true);
    }
  };

  const handleBackToList = () => {
    setSelectedAd(null);
    setIsMobileDetailView(false);
  };

  const queries = [...new Set(ads.map(ad => ad.query))];

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-orange-500 mx-auto"></div>
          <p className="text-gray-400 mt-4">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-900 text-gray-100">
      {/* Header */}
      <header className="bg-gray-800 border-b border-gray-700 p-4">
        <div className="max-w-7xl mx-auto flex flex-col sm:flex-row gap-4 items-center justify-between">
          <div className="flex items-center space-x-4">
            <h1 className="text-2xl font-bold text-orange-500 flex items-center gap-2">
              <img src="/img/favicon/favicon-32x32.png" alt="Logo" className="w-8 h-8" />
              Hysterka
            </h1>
            <span className="text-sm text-gray-400">
              Welcome, {user?.name}
            </span>
          </div>
          
          <div className="flex items-center gap-4">
            <span className="text-sm text-gray-400">
              {filteredAds.length} z {ads.length} inzerátov
            </span>
            
            <select
              value={filter}
              onChange={e => {
                setFilter(e.target.value);
                setSelectedAd(null);
                setIsMobileDetailView(false);
              }}
              className="bg-gray-700 border border-gray-600 px-3 py-2 rounded-lg text-gray-100 focus:ring-2 focus:ring-orange-500 focus:border-transparent"
            >
              <option value="all">🔍 Všetky vyhľadávania</option>
              {queries.map(q => (
                <option key={q} value={q}>
                  {q}
                </option>
              ))}
            </select>

            <button
              onClick={handleLogout}
              className="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white font-medium transition-colors duration-200"
            >
              Logout
            </button>
          </div>
        </div>
      </header>

      {/* Mobile Detail View */}
      {isMobileDetailView && selectedAd ? (
        <div className="lg:hidden fixed inset-0 bg-gray-900 z-50 flex flex-col">
          {/* Mobile Header with Back Button */}
          <div className="bg-gray-800 border-b border-gray-700 p-4 flex items-center gap-3">
            <button
              onClick={handleBackToList}
              className="flex items-center justify-center w-10 h-10 rounded-full bg-gray-700 hover:bg-gray-600 transition-colors"
            >
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <h1 className="text-lg font-semibold text-white truncate flex-1">
              {selectedAd.is_available === false && "🚫 "}
              {selectedAd.title}
            </h1>
          </div>
          
          {/* Mobile Detail Content */}
          <div className="flex-1 overflow-y-auto">
            {(() => {
              const { city } = parseLocation(selectedAd.location || "");
              return (
                <div className="p-4">
                  {/* Price and Meta */}
                  <div className="border-b border-gray-700 pb-4 mb-6">
                    <div className="flex flex-wrap gap-3 text-sm text-gray-400 mb-4 items-center">
                      <span className="text-xl font-semibold text-green-400">
                        {selectedAd.price}
                      </span>
                      {selectedAd.found_at && (
                        <span>📅 Nájdené: {formatFoundDate(selectedAd.found_at)}</span>
                      )}
                      {selectedAd.found_at && (
                        <span>⏰ {formatRelativeTime(selectedAd.found_at)}</span>
                      )}
                      {city && (
                        <a
                          href={createMapUrl(city)}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-400 hover:text-blue-300 transition-colors"
                        >
                          📍 {city}
                        </a>
                      )}
                      {selectedAd.view_count && <span>👁️ {selectedAd.view_count}</span>}
                      <span className="text-orange-400">🔍 {selectedAd.query}</span>
                      {selectedAd.is_available === false && (
                        <span className="text-red-400">🚫 Už nie je dostupné</span>
                      )}
                      {selectedAd.last_updated && (
                        <span className="text-gray-500">🔄 Aktualizované: {formatRelativeTime(selectedAd.last_updated)}</span>
                      )}
                    </div>
                  </div>

                  {/* Images */}
                  {selectedAd.images.length > 0 && (
                    <div className="mb-6">
                      <h3 className="text-lg font-semibold text-gray-200 mb-3">Obrázky</h3>
                      <div className="grid grid-cols-2 gap-3">
                        {selectedAd.images.map((imagePath, index) => (
                          <img
                            key={index}
                            src={imagePath}
                            alt={`Product ${index + 1}`}
                            className="w-full h-32 object-cover rounded-lg cursor-pointer hover:opacity-80 transition-opacity"
                            onClick={() => handleImageClick(index)}
                          />
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Description */}
                  {selectedAd.description && (
                    <div className="mb-6">
                      <h3 className="text-lg font-semibold text-gray-200 mb-3">Popis</h3>
                      <p className="text-gray-300 whitespace-pre-wrap">{selectedAd.description}</p>
                    </div>
                  )}

                  {/* Contact */}
                  {selectedAd.contact && (
                    <div className="mb-6">
                      <h3 className="text-lg font-semibold text-gray-200 mb-3">Kontakt</h3>
                      <p className="text-gray-300">{selectedAd.contact}</p>
                    </div>
                  )}

                  {/* Original Link */}
                  <div className="mt-6">
                    <a
                      href={selectedAd.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center px-4 py-2 bg-orange-500 hover:bg-orange-600 rounded-lg text-white font-medium transition-colors duration-200"
                    >
                      Otvoriť inzerát
                    </a>
                  </div>
                </div>
              );
            })()}
          </div>
        </div>
      ) : (
        /* Desktop Layout */
        <div className="flex h-[calc(100vh-80px)]">
          {/* Sidebar */}
          <div className="w-80 bg-gray-800 border-r border-gray-700 overflow-y-auto">
            <div className="p-4">
              <h2 className="text-lg font-semibold text-gray-200 mb-4">Nájdené inzeráty</h2>
              <div className="space-y-2">
                {filteredAds.map((ad) => (
                  <div
                    key={ad.id}
                    onClick={() => handleAdSelect(ad)}
                    className={`p-3 rounded-lg cursor-pointer transition-all duration-200 ${
                      selectedAd?.id === ad.id
                        ? 'bg-orange-500 text-white'
                        : 'bg-gray-700 hover:bg-gray-600 text-gray-300'
                    }`}
                  >
                    <div className="flex items-start gap-3">
                      {ad.images.length > 0 && (
                        <img
                          src={ad.images[0]}
                          alt="Product"
                          className="w-12 h-12 object-cover rounded"
                        />
                      )}
                      <div className="flex-1 min-w-0">
                        <h3 className="font-medium truncate">
                          {ad.is_available === false && "🚫 "}
                          {ad.title}
                        </h3>
                        <p className="text-sm text-green-400 font-semibold">{ad.price}</p>
                        <p className="text-xs text-gray-400">
                          {ad.found_at && formatRelativeTime(ad.found_at)}
                        </p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Main Content */}
          <div className="flex-1 overflow-y-auto">
            {selectedAd ? (
              <div className="p-6">
                {(() => {
                  const { city } = parseLocation(selectedAd.location || "");
                  return (
                    <>
                      {/* Header */}
                      <div className="border-b border-gray-700 pb-6 mb-6">
                        <h1 className="text-3xl font-bold text-gray-200 mb-4">
                          {selectedAd.is_available === false && "🚫 "}
                          {selectedAd.title}
                        </h1>
                        <div className="flex flex-wrap gap-4 text-sm text-gray-400 items-center">
                          <span className="text-2xl font-semibold text-green-400">
                            {selectedAd.price}
                          </span>
                          {selectedAd.found_at && (
                            <span>📅 Nájdené: {formatFoundDate(selectedAd.found_at)}</span>
                          )}
                          {selectedAd.found_at && (
                            <span>⏰ {formatRelativeTime(selectedAd.found_at)}</span>
                          )}
                          {city && (
                            <a
                              href={createMapUrl(city)}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="text-blue-400 hover:text-blue-300 transition-colors"
                            >
                              📍 {city}
                            </a>
                          )}
                          {selectedAd.view_count && <span>👁️ {selectedAd.view_count}</span>}
                          <span className="text-orange-400">🔍 {selectedAd.query}</span>
                          {selectedAd.is_available === false && (
                            <span className="text-red-400">🚫 Už nie je dostupné</span>
                          )}
                          {selectedAd.last_updated && (
                            <span className="text-gray-500">🔄 Aktualizované: {formatRelativeTime(selectedAd.last_updated)}</span>
                          )}
                        </div>
                      </div>

                      {/* Images */}
                      {selectedAd.images.length > 0 && (
                        <div className="mb-8">
                          <h3 className="text-xl font-semibold text-gray-200 mb-4">Obrázky</h3>
                          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            {selectedAd.images.map((imagePath, index) => (
                              <img
                                key={index}
                                src={imagePath}
                                alt={`Product ${index + 1}`}
                                className="w-full h-48 object-cover rounded-lg cursor-pointer hover:opacity-80 transition-opacity"
                                onClick={() => handleImageClick(index)}
                              />
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Description */}
                      {selectedAd.description && (
                        <div className="mb-8">
                          <h3 className="text-xl font-semibold text-gray-200 mb-4">Popis</h3>
                          <p className="text-gray-300 whitespace-pre-wrap">{selectedAd.description}</p>
                        </div>
                      )}

                      {/* Contact */}
                      {selectedAd.contact && (
                        <div className="mb-8">
                          <h3 className="text-xl font-semibold text-gray-200 mb-4">Kontakt</h3>
                          <p className="text-gray-300">{selectedAd.contact}</p>
                        </div>
                      )}

                      {/* Original Link */}
                      <div className="mt-8">
                        <a
                          href={selectedAd.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center px-6 py-3 bg-orange-500 hover:bg-orange-600 rounded-lg text-white font-medium transition-colors duration-200"
                        >
                          Otvoriť inzerát
                        </a>
                      </div>
                    </>
                  );
                })()}
              </div>
            ) : (
              <div className="flex items-center justify-center h-full text-gray-400">
                <div className="text-center">
                  <svg className="w-16 h-16 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <p>Vyberte inzerát z ľavého panelu</p>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default Dashboard; 