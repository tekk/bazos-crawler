// Test parsing function
function parseLocation(location) {
  if (!location) return { city: "", postalCode: "" };
  
  // Try to match Slovak postal code patterns
  // Pattern 1: 5 digits + space + 2 digits (e.g., "851 06")
  const pattern1 = location.match(/(\d{3}\s*\d{2})/);
  if (pattern1) {
    const postalCode = pattern1[1];
    let city = location.replace(/\d{3}\s*\d{2}/g, "").trim();
    return { city, postalCode };
  }
  
  // Pattern 2: City + 5 continuous digits (e.g., "Bratislava85106")
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
}

// Test cases
console.log('Test 1 - Bratislava851 06:', parseLocation('Bratislava851 06'));
console.log('Test 2 - Košice040 01:', parseLocation('Košice040 01'));
console.log('Test 3 - Bratislava 811 05:', parseLocation('Bratislava 811 05'));
console.log('Test 4 - Praha:', parseLocation('Praha'));
console.log('Test 5 - Nitra95001:', parseLocation('Nitra95001'));