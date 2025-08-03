
import React, { useEffect, useState } from "react";
import Lightbox from "yet-another-react-lightbox";
import "yet-another-react-lightbox/styles.css";
import { formatDistanceToNow } from "date-fns";
import { sk } from "date-fns/locale";

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

const App: React.FC = () => {
  const [ads, setAds] = useState<Ad[]>([]);
  const [selectedAd, setSelectedAd] = useState<Ad | null>(null);
  const [filter, setFilter] = useState("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lightboxOpen, setLightboxOpen] = useState(false);
  const [lightboxIndex, setLightboxIndex] = useState(0);
  const [isMobileDetailView, setIsMobileDetailView] = useState(false);

  useEffect(() => {
    fetch("./data/found_items/index.json")
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        setAds(data.ads || []);
        setLoading(false);
      })
      .catch(err => {
        console.error("Error loading data:", err);
        setError("Chyba pri načítavaní dát. Skontrolujte, či sú k dispozícii nájdené inzeráty.");
        setLoading(false);
      });
  }, []);

  // Handle screen resize to reset mobile detail view on desktop
  useEffect(() => {
    const handleResize = () => {
      if (window.innerWidth >= 1024 && isMobileDetailView) {
        setIsMobileDetailView(false);
      }
    };

    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, [isMobileDetailView]);

  const queries = Array.from(new Set(ads.map(a => a.query))).sort();
  const filteredAds = filter === "all" ? ads : ads.filter(a => a.query === filter);

  // Function to parse location into city only (postal code stored but not displayed)
  const parseLocation = (location: string) => {
    if (!location) return { city: "", postalCode: "" };
    
    // Try to match Slovak postal code patterns
    // Pattern 1: 3 digits + space + 2 digits (e.g., "851 06")
    const pattern1 = location.match(/(\d{3}\s*\d{2})/);
    if (pattern1) {
      const postalCode = pattern1[1];
      let city = location.replace(/\d{3}\s*\d{2}/g, "").trim();
      return { city, postalCode };
    }
    
    // Pattern 2: City + 5-6 continuous digits (e.g., "Bratislava85106")
    const pattern2 = location.match(/([a-zA-ZáčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ\s]+?)(\d{5,6})/);
    if (pattern2) {
      const city = pattern2[1].trim();
      const postalCode = pattern2[2];
      return { city, postalCode };
    }
    
    // Pattern 3: 5 digits anywhere in string
    const pattern3 = location.match(/(\d{5})/);
    if (pattern3) {
      const postalCode = pattern3[1];
      let city = location.replace(/\d{5}/g, "").replace(/\s+/g, " ").trim();
      return { city, postalCode };
    }
    
    // If no postal code found, return as city
    return { city: location.trim(), postalCode: "" };
  };

  // Function to format found date in Slovak format
  const formatFoundDate = (dateString: string) => {
    try {
      const date = new Date(dateString);
      const dayNames = ['nedeľa', 'pondelok', 'utorok', 'streda', 'štvrtok', 'piatok', 'sobota'];
      const dayName = dayNames[date.getDay()];
      
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      const hours = String(date.getHours()).padStart(2, '0');
      const minutes = String(date.getMinutes()).padStart(2, '0');
      
      return `${dayName}, ${year}-${month}-${day}, ${hours}:${minutes}`;
    } catch {
      return dateString;
    }
  };

  // Function to create Google Maps search URL
  const createMapUrl = (city: string) => {
    const encodedCity = encodeURIComponent(city);
    return `https://www.google.com/maps/search/${encodedCity}`;
  };

  // Function to format relative time
  const formatRelativeTime = (foundAt: string) => {
    try {
      const date = new Date(foundAt);
      return formatDistanceToNow(date, { addSuffix: true, locale: sk });
    } catch {
      return "";
    }
  };

  // Function to handle image click
  const handleImageClick = (index: number) => {
    setLightboxIndex(index);
    setLightboxOpen(true);
  };

  // Function to handle ad selection with mobile responsiveness
  const handleAdSelect = (ad: Ad) => {
    setSelectedAd(ad);
    // Check if mobile screen size
    if (window.innerWidth < 1024) { // lg breakpoint
      setIsMobileDetailView(true);
    }
  };

  // Function to go back from mobile detail view
  const handleBackToList = () => {
    setIsMobileDetailView(false);
    setSelectedAd(null);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-900 text-gray-100 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
          <p>Načítavam inzeráty...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-900 text-gray-100 flex items-center justify-center">
        <div className="text-center">
          <div className="text-red-400 text-xl mb-4">⚠️</div>
          <p className="text-red-300">{error}</p>
        </div>
      </div>
          );
   }

  return (
    <div className="min-h-screen bg-gray-900 text-gray-100">
      {/* Header */}
      <header className="bg-gray-800 border-b border-gray-700 p-4">
        <div className="max-w-7xl mx-auto flex flex-col sm:flex-row gap-4 items-center justify-between">
          <h1 className="text-2xl font-bold text-orange-500 flex items-center gap-2">
            <img src="/img/favicon/favicon-32x32.png" alt="Logo" className="w-8 h-8" />
            Bazos Crawler
          </h1>
          
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
              className="bg-gray-700 border border-gray-600 px-3 py-2 rounded-lg text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="all">🔍 Všetky vyhľadávania</option>
              {queries.map(q => (
                <option key={q} value={q}>
                  {q}
                </option>
              ))}
            </select>
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
                      <span className="text-blue-400">🔍 {selectedAd.query}</span>
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
                          <div key={index} className="group cursor-pointer">
                            <img
                              src={imagePath}
                              alt={`${selectedAd.title} - obrázok ${index + 1}`}
                              className="w-full h-32 object-cover rounded-lg bg-gray-700 transition-transform group-hover:scale-105"
                              onClick={() => handleImageClick(index)}
                              onError={(e) => {
                                const target = e.target as HTMLImageElement;
                                target.style.display = 'none';
                              }}
                            />
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Description */}
                  {selectedAd.description && (
                    <div className="mb-6">
                      <h3 className="text-lg font-semibold text-gray-200 mb-3">Popis</h3>
                      <div className="bg-gray-700 rounded-lg p-4">
                        <p className="text-gray-300 whitespace-pre-wrap leading-relaxed">
                          {selectedAd.description}
                        </p>
                      </div>
                    </div>
                  )}

                  {/* Contact */}
                  {selectedAd.contact && (
                    <div className="mb-6">
                      <h3 className="text-lg font-semibold text-gray-200 mb-3">Kontakt</h3>
                      <div className="bg-gray-700 rounded-lg p-4">
                        <p className="text-gray-300 whitespace-pre-wrap">
                          {selectedAd.contact}
                        </p>
                      </div>
                    </div>
                  )}

                  {/* Actions */}
                  <div className="flex flex-col gap-3 pt-4 border-t border-gray-700">
                    <a
                      href={selectedAd.htmlPath}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                    >
                      📄 Uložené HTML
                    </a>
                    <a
                      href={`https://bazos.sk${selectedAd.htmlPath.includes('http') ? '' : '/'}${selectedAd.id}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center justify-center px-4 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                    >
                      🔗 Pôvodný inzerát
                    </a>
                  </div>
                </div>
              );
            })()}
          </div>
        </div>
      ) : null}

      {/* Desktop and Mobile List View */}
      <div className={`max-w-7xl mx-auto p-4 ${isMobileDetailView ? 'lg:block hidden' : ''}`}>
        <div className="grid lg:grid-cols-3 gap-6">
          {/* Ads List */}
          <div className="lg:col-span-1">
            <div className="bg-gray-800 border border-gray-700 rounded-lg">
              <div className="p-4 border-b border-gray-700">
                <h2 className="font-semibold text-gray-200">Nájdené inzeráty</h2>
              </div>
              
              <div className="max-h-[calc(100vh-200px)] overflow-y-auto">
                {filteredAds.length === 0 ? (
                  <div className="p-4 text-center text-gray-400">
                    Žiadne inzeráty nenájdené
                  </div>
                ) : (
                  <div className="divide-y divide-gray-700">
                    {filteredAds.map(ad => {
                      const { city } = parseLocation(ad.location || "");
                      return (
                        <div
                          key={ad.id}
                          onClick={() => handleAdSelect(ad)}
                          className={`p-4 cursor-pointer transition-colors hover:bg-gray-700 ${
                            selectedAd?.id === ad.id ? "bg-gray-700 border-r-4 border-blue-500" : ""
                          } ${ad.is_available === false ? "opacity-60" : ""}`}
                        >
                          <div className="flex gap-3">
                            {/* Text content */}
                            <div className="flex-1 min-w-0">
                              <div className={`font-medium text-white mb-1 line-clamp-2 ${
                                ad.is_available === false ? "line-through" : ""
                              }`}>
                                {ad.is_available === false && "🚫 "}
                                {ad.title}
                              </div>
                              <div className="text-sm text-gray-400 flex justify-between items-center">
                                <span className="font-semibold text-green-400">{ad.price}</span>
                                <div className="text-right">
                                  {ad.found_at && (
                                    <div className="text-xs text-gray-500">
                                      Nájdené:
                                    </div>
                                  )}
                                  <div className="text-xs">
                                    {ad.found_at ? formatFoundDate(ad.found_at) : ad.date}
                                  </div>
                                </div>
                              </div>
                              <div className="text-xs text-gray-400 flex justify-between items-center mt-1">
                                {city && (
                                  <a
                                    href={createMapUrl(city)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-blue-400 hover:text-blue-300 transition-colors"
                                    onClick={(e) => e.stopPropagation()}
                                  >
                                    📍 {city}
                                  </a>
                                )}
                                {ad.view_count && (
                                  <span className="text-gray-500">👁️ {ad.view_count}</span>
                                )}
                              </div>
                              <div className="text-xs text-blue-400 mt-1">{ad.query}</div>
                            </div>
                            
                            {/* Product image */}
                            {ad.images && ad.images.length > 0 && (
                              <div className="flex-shrink-0">
                                <img
                                  src={ad.images[0]}
                                  alt={ad.title}
                                  className={`w-20 h-20 object-cover rounded-lg bg-gray-700 ${
                                    ad.is_available === false ? "grayscale" : ""
                                  }`}
                                  onError={(e) => {
                                    const target = e.target as HTMLImageElement;
                                    target.style.display = 'none';
                                  }}
                                />
                              </div>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Ad Detail - Hidden on mobile when in mobile detail view */}
          <div className={`lg:col-span-2 ${isMobileDetailView ? 'hidden' : 'hidden lg:block'}`}>
            <div className="bg-gray-800 border border-gray-700 rounded-lg min-h-[600px]">
              {selectedAd ? (
                (() => {
                  const { city } = parseLocation(selectedAd.location || "");
                  return (
                    <div className="p-6">
                      {/* Title and Meta */}
                      <div className="border-b border-gray-700 pb-4 mb-6">
                        <h1 className="text-2xl font-bold text-white mb-2">
                          {selectedAd.is_available === false && "🚫 "}
                          {selectedAd.title}
                        </h1>
                        
                        <div className="flex flex-wrap gap-4 text-sm text-gray-400 items-center">
                          <span className="text-lg font-semibold text-green-400">
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
                          <span className="text-blue-400">🔍 {selectedAd.query}</span>
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
                          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {selectedAd.images.map((imagePath, index) => (
                              <div key={index} className="group cursor-pointer">
                                <img
                                  src={imagePath}
                                  alt={`${selectedAd.title} - obrázok ${index + 1}`}
                                  className="w-full h-48 object-cover rounded-lg bg-gray-700 transition-transform group-hover:scale-105"
                                  onClick={() => handleImageClick(index)}
                                  onError={(e) => {
                                    const target = e.target as HTMLImageElement;
                                    target.style.display = 'none';
                                  }}
                                />
                              </div>
                            ))}
                          </div>
                        </div>
                      )}

                      {/* Description */}
                      {selectedAd.description && (
                        <div className="mb-6">
                          <h3 className="text-lg font-semibold text-gray-200 mb-3">Popis</h3>
                          <div className="bg-gray-700 rounded-lg p-4">
                            <p className="text-gray-300 whitespace-pre-wrap leading-relaxed">
                              {selectedAd.description}
                            </p>
                          </div>
                        </div>
                      )}

                      {/* Contact */}
                      {selectedAd.contact && (
                        <div className="mb-6">
                          <h3 className="text-lg font-semibold text-gray-200 mb-3">Kontakt</h3>
                          <div className="bg-gray-700 rounded-lg p-4">
                            <p className="text-gray-300 whitespace-pre-wrap">
                              {selectedAd.contact}
                            </p>
                          </div>
                        </div>
                      )}

                      {/* Actions */}
                      <div className="flex gap-3 pt-4 border-t border-gray-700">
                        <a
                          href={selectedAd.htmlPath}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                        >
                          📄 Uložené HTML
                        </a>
                        <a
                          href={`https://bazos.sk${selectedAd.htmlPath.includes('http') ? '' : '/'}${selectedAd.id}`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                        >
                          🔗 Pôvodný inzerát
                        </a>
                      </div>
                    </div>
                  );
                })()
              ) : (
                <div className="flex items-center justify-center h-[600px] text-gray-400">
                  <div className="text-center">
                    <div className="text-4xl mb-4">🛒</div>
                    <p className="text-xl">Vyberte inzerát na zobrazenie detailov</p>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Lightbox */}
      {selectedAd && selectedAd.images.length > 0 && (
        <Lightbox
          open={lightboxOpen}
          close={() => setLightboxOpen(false)}
          index={lightboxIndex}
          slides={selectedAd.images.map((src, index) => ({
            src: src,
            alt: `${selectedAd.title} - obrázok ${index + 1}`
          }))}
          carousel={{
            finite: true,
          }}
        />
      )}
    </div>
  );
};

export default App;
