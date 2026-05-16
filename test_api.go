package main

import (
	"fmt"
	"io"
	"log"
	"net/http"
	"time"
)

func main() {
	client := &http.Client{}
	// Run for 15 seconds then exit
	deadline := time.After(15 * time.Second)
	
	fmt.Println("Starting rate limit test...")
	
	for {
		select {
		case <-deadline:
			fmt.Println("Test finished.")
			return
		default:
			go makeRequest(client)
			time.Sleep(50 * time.Millisecond)
		}
	}
}

func makeRequest(client *http.Client) {
	resp, err := client.Get("https://cts-backend-1.onrender.com/api/positions")
	if err != nil {
		log.Printf("request error: %v", err)
		return
	}
	defer resp.Body.Close()

	fmt.Printf("Status: %d\n", resp.StatusCode)

	if resp.StatusCode == 429 {
		fmt.Println("RATE LIMITED!")
	}

	// Read body to ensure connection reuse
	_, _ = io.ReadAll(resp.Body)
}
