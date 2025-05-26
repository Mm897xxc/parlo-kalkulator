
import React, { useState } from "https://cdn.skypack.dev/react";
import { createRoot } from "https://cdn.skypack.dev/react-dom/client";

function SavingsCalculator() {
  const [subscribers, setSubscribers] = useState(25000);
  const [campaigns, setCampaigns] = useState(4);
  const totalEmails = subscribers * campaigns;
  const pricePerEmail = 0.00085;
  const parloCost = (totalEmails * pricePerEmail).toFixed(2);

  const plans = [
    { name: "Mailchimp", monthlyCost: 270 },
    { name: "MailerLite", monthlyCost: 159 },
    { name: "ConvertKit", monthlyCost: 199 },
    { name: "AWeber", monthlyCost: 250 },
    { name: "Campaign Monitor", monthlyCost: 219 },
    { name: "Get Response", monthlyCost: 174 },
    { name: "Constant Contact", monthlyCost: 310 }
  ];

  return (
    <div style={{ maxWidth: 800, margin: '0 auto', color: 'white' }}>
      <h1>Mjesečni trošak s Parlom: €{parloCost}</h1>
      <label>Pretplatnici:</label>
      <input type="number" value={subscribers} onChange={(e) => setSubscribers(Number(e.target.value))} />
      <br />
      <label>Kampanja mjesečno:</label>
      <input type="number" value={campaigns} onChange={(e) => setCampaigns(Number(e.target.value))} />
      <h3>Usporedba:</h3>
      <ul>
        {plans.map((plan) => (
          <li key={plan.name}>
            {plan.name}: €{plan.monthlyCost}
          </li>
        ))}
      </ul>
      <button style={{ marginTop: 20, padding: '1rem 2rem', fontSize: '1.2rem' }}>Započni besplatno</button>
    </div>
  );
}

const root = createRoot(document.getElementById("root"));
root.render(<SavingsCalculator />);
