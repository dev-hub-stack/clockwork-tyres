The following is a transcript of the discussion regarding reporting features, CRM integration, and future project planning:

* **Ahmad Sheikh**: So, can you go back to the previous screen up there? Yeah, so by brand. Sales by brand. So, this will be the screen down here.  
* **George Varkey**: Yeah.  
* **Ahmad Sheikh**: Yeah, this one. So, this will be by month and will have a date range, right? We can go to the previous month as well?  
* **George Varkey**: Yeah, this will have the date range. So, we're looking for months of January to April, or six months, 12 months, or any particular months we get there.  
* **Ahmad Sheikh**: The financial months? Meaning the fiscal year from July to the next financial cycle?  
* **George Varkey**: No, we take it from January to December. We just consider the normal yearly calendar. The first quarter is January to March. You can select the months like January till December, January till June, or January till March.  
* **Ahmad Sheikh**: So, it will be scrollable, like January, February, March, April, May, and the next months will be scrollable?  
* **George Varkey**: Yeah, if you can. Whatever works best, because I like what you've done with the whole design of the reporting. It's very clean, very intuitive, and it's a good interface. So, if you can come up with something better, by all means, but this is basically how I want the data to be shown.  
* **Ahmad Sheikh**: So, this sales by brand will include counting regardless of the sales from the tuner store or wholesale? It will count the invoices in CRM?  
* **George Varkey**: Yeah, it'll count the invoices in CRM. Not the orders placed on Tunerstop or the orders placed on wholesale. Only the invoices in CRM because every bit of data can be captured there. The whole point of the CRM is to visualize every data point.  
* **Ahmad Sheikh**: So, that value is the total invoice value, right?  
* **George Varkey**: No, not the total invoice value; the brand's value. In an invoice, if there are two brands, then that will become incorrect data because you're giving the total for the invoice rather than only the line item for that specific brand.  
* **Ahmad Sheikh**: Right. So, it will be the sum of that line item for that particular brand. Got it.  
* **George Varkey**: Yes. Same for model and same for size. We have different sizes, like 17 or 8.5. We're not looking at any particular brand or model; we're just looking at the size across all brands and models. What is the total count, quantity, and value for invoices that have this size?  
* **George Varkey**: Then vehicle. Here we have year, make, model, and generation. The reason I added generation is that 2020 through 2024 will be the same generation of car. For example, G82 and G83 are generations from 2014 to 2020\. The year doesn't matter as much as this data point. Rather than having seven different lines for a BMW M4 across different years, we group it into the generation, such as G82/G83 BMW M4. It will show the make, model, generation, and the number of invoices that have that vehicle every month.  
* **George Varkey**: Then dealer would be whoever the wholesale registered customers are by name. Wholesale customer one, two, and three; the total quantity of wheels they purchased in this month and the total value.  
* **George Varkey**: Specifically for SKU data as well: for that particular SKU, how many quantity sold and the value. Then channel: wholesale and retail; total quantity sold and value for each.  
* **George Varkey**: For sorting, we can sort each month high to low by quantity or value. We can export the data to CSV or PDF. We can also filter it. For example, if we are looking at by model, we can further filter that data to only retail or only wholesale, or by a specific wholesale customer.  
* **George Varkey**: The profit report is basically this. We have these profits calculated over here.  
* **Ahmad Sheikh**: Under the invoices section, the profit we are calculating. Against each invoice, you want the value of that profit and margin?  
* **George Varkey**: I don't think profit by order is necessary because we already have that data. It doesn't make sense to have that screen particularly. But let's say profit by brand. It gives you the sum total of all the invoices for that brand.  
* **George Varkey**: Same for the model, dealer, SKU, and channel. I don't think profit by size is required. For vehicle, maybe we can see later, I'll just leave it for now.  
* **George Varkey**: We can filter it the same way as the sales reports. For the inventory report, we already have it, but for inventory by month, we want to see all items in stock, quantity added each month, and quantity sold each month. Then you can click on it and see all the invoices.  
* **George Varkey**: There is a website report from Tunis for searches, but let's leave that for now and come back to it.  
* **George Varkey**: If you can do something to track user performance for each salesman on each invoice, just the basic quantity sold and value, that should be enough.  
* **Ahmad Sheikh**: Right. Reload it.  
* **George Varkey**: Actually, I don't think we need that as a separate item. Under the sales and profit report, we can just use a filter to select the user and get that data. So separate by user is not required.  
* **George Varkey**: For inventory by month, this is by SKU, but we can also have it grouped by model and full brand to see total quantity added and total quantity sold.  
* **Ahmad Sheikh**: Okay, I will start working on this. If I have questions, I will post them during development. Does one week sound like enough?  
* **George Varkey**: Another thing I wanted to discuss was Clockwork. I wanted to upgrade this Clockwork admin and merge it with this reporting, but like a separate thing not connected to Tunerstop. Like how we built the CRM; it's very good.  
* **Ahmad Sheikh**: I wanted to let you know that the technology used to build that is officially being retired. That's why I moved to this new technology for the CRM. It was Laravel Voyager, but they are retiring it because there are better options, like Laravel Filament.  
* **George Varkey**: I'm planning to update the whole idea of the Clockwork thing. Same concept, but Clockwork doesn't have all these reporting features or the clean inventory management we just discussed. I want to focus it on tires as a main category, with all these different data points, a dealer dashboard, and bulk ordering.  
* **Ahmad Sheikh**: So we can create a separate copy of that CRM admin and link it to this front end and customize it. That's doable.  
* **George Varkey**: How long do you think that will take?  
* **Ahmad Sheikh**: It's a very big system with multi-vendor features, so it will take at least one or two months to migrate.  
* **George Varkey**: One or two months is fine. The front end is already there and perfect. We just need to add the tire category and design the database.  
* **Ahmad Sheikh**: You can send me the details or mockups for the tire page, and I can start mapping out how the back end will work.  
* **George Varkey**: I will work on that. I've noted a few small things I need to think about, but basically everything from the CRM. Looking through the CRM, we have the dashboard with pending payments, pending orders, today's orders, warranty claims, and daily active users. We can have total number of products and registered wholesale customers.  
* **George Varkey**: I guess all of this will come under reporting: top products, top dealers, performers, consignments, invoices, warranty claims, warehouses, inventory grid and movement, customers, and log activities.  
* **George Varkey**: So I need to give you a mockup of how the tire section will look on the Clockwork front end. That front end is the latest Angular technology, right?  
* **Ahmad Sheikh**: Yes, it's the latest. I switched to it when I heard they were discontinuing the old framework, which is why I discarded the previous reporting demo.  
* **George Varkey**: This is so much better. You mentioned it might take two months to integrate the new CRM back end to this?  
* **Ahmad Sheikh**: I might be able to wrap it up more quickly with the help of AI, but given the scope and features of the project, that's the estimate.  
* **George Varkey**: Understood. Let me work on a document to explain what needs to change or be added. I'll send that across. By next Monday, we can have the reports ready, and that gives us two months to test the CRM, see what issues we face, and ensure it's a solid product.  
* **George Varkey**: Also, let me know what I owe you for the CRM and previous projects.  
* **Ahmad Sheikh**: Sure. Thank you.  
* **George Varkey**: Thanks so much.

Sources:

* [Sync \- 2026/03/23 17:54 PKT – Recording](https://drive.google.com/open?id=1p279ujhvv3tlhVOX5ZIiToDVDLv8wTrp)