
import React, { useEffect, useState } from "react";

interface Ad {
  id: string;
  title: string;
  price: string;
  date: string;
  query: string;
  images: string[];
  htmlPath: string;
  description: string;
  contact: string;
  location?: string;
  view_count?: string;
  category?: string;
}

const App: React.FC = () => {
  const [ads, setAds] = useState<Ad[]>([]);
  const [selectedAd, setSelectedAd] = useState<Ad | null>(null);
  const [filter, setFilter] = useState("all");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

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

  const queries = Array.from(new Set(ads.map(a => a.query))).sort();
  const filteredAds = filter === "all" ? ads : ads.filter(a => a.query === filter);

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
          <h1 className="text-2xl font-bold text-blue-400">🛒 Bazoš Crawler</h1>
          
          <div className="flex items-center gap-4">
            <span className="text-sm text-gray-400">
              {filteredAds.length} z {ads.length} inzerátov
            </span>
            
            <select
              value={filter}
              onChange={e => {
                setFilter(e.target.value);
                setSelectedAd(null);
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

      <div className="max-w-7xl mx-auto p-4">
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
                    {filteredAds.map(ad => (
                      <div
                        key={ad.id}
                        onClick={() => setSelectedAd(ad)}
                        className={`p-4 cursor-pointer transition-colors hover:bg-gray-700 ${
                          selectedAd?.id === ad.id ? "bg-gray-700 border-r-4 border-blue-500" : ""
                        }`}
                      >
                        <div className="font-medium text-gray-200 mb-1 line-clamp-2">
                          {ad.title}
                        </div>
                        <div className="text-sm text-gray-400 flex justify-between items-center">
                          <span className="font-semibold text-green-400">{ad.price}</span>
                          <span>{ad.date}</span>
                        </div>
                        {ad.location && (
                          <div className="text-xs text-gray-500 mt-1">📍 {ad.location}</div>
                        )}
                        <div className="text-xs text-blue-400 mt-1">{ad.query}</div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Ad Detail */}
          <div className="lg:col-span-2">
            <div className="bg-gray-800 border border-gray-700 rounded-lg min-h-[600px]">
              {selectedAd ? (
                <div className="p-6">
                  {/* Title and Meta */}
                  <div className="border-b border-gray-700 pb-4 mb-6">
                    <h1 className="text-2xl font-bold text-gray-100 mb-2">
                      {selectedAd.title}
                    </h1>
                    
                    <div className="flex flex-wrap gap-4 text-sm text-gray-400">
                      <span className="text-lg font-semibold text-green-400">
                        {selectedAd.price}
                      </span>
                      <span>📅 {selectedAd.date}</span>
                      {selectedAd.location && <span>📍 {selectedAd.location}</span>}
                      {selectedAd.view_count && <span>👁️ {selectedAd.view_count}</span>}
                      <span className="text-blue-400">🔍 {selectedAd.query}</span>
                    </div>
                  </div>

                  {/* Images */}
                  {selectedAd.images.length > 0 && (
                    <div className="mb-6">
                      <h3 className="text-lg font-semibold text-gray-200 mb-3">Obrázky</h3>
                      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
                        {selectedAd.images.map((imagePath, index) => (
                          <div key={index} className="group">
                            <img
                              src={imagePath}
                              alt={`${selectedAd.title} - obrázok ${index + 1}`}
                              className="w-full h-48 object-cover rounded-lg bg-gray-700 transition-transform group-hover:scale-105"
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
    </div>
  );
};

export default App;
